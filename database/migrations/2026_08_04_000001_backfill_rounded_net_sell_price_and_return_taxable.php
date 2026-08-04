<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes a per-unit rounding bug (tenant tables).
 *
 * The per-unit net_sell_price(_usd) columns had drifted to DECIMAL(15,2) in production
 * (the money() macro declares DECIMAL(45,8)). At 2 decimals the DB rounds every write, so
 * 0.584 is stored as 0.58. Customer returns then multiplied that rounded per-unit by quantity,
 * storing a wrong taxable total (0.58 * 24 = 13.92 instead of 0.584 * 24 = 14.02).
 *
 * The return code now derives the taxable base from full-precision price - unit_discount_amount,
 * so new returns are correct. This migration repairs the schema + existing rows:
 *   1. sale_items: widen net_sell_price(_usd) back to DECIMAL(45,8), then restore them to
 *      price - unit_discount_amount at full precision. Money totals (total_net_sell_price,
 *      total_price, ...) are intentionally left as DECIMAL(15,2) / untouched.
 *   2. customer_return_items: recompute the taxable base / tax split from the item's own
 *      full-precision price + discount (mirrors CustomerReturnService::directTaxableBreakdown).
 *   3. customer_returns: re-derive the taxable/tax subtotals from the corrected items.
 *
 * All statements are guarded and touch only rows that are actually wrong, so the migration
 * is idempotent and a no-op on tenants without the relevant columns / on already-correct data.
 * Note: the ALTER on step 1 rebuilds sale_items and may run for a while on large tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->widenPerUnitColumns();
        $this->fixSaleItems();
        $this->fixReturnItems();
        $this->fixReturnSubtotals();
    }

    private function widenPerUnitColumns(): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        // Restore the DECIMAL(45,8) the money() macro intends, so the per-unit value can
        // actually hold precision (otherwise the backfill below is silently re-rounded).
        // Only alter columns still stuck at < 8 decimals, to avoid rebuilding correct tables.
        $database = DB::getDatabaseName();

        foreach (['net_sell_price', 'net_sell_price_usd', 'total_tax_amount', 'total_tax_amount_usd', 'total_net_sell_price', 'total_net_sell_price_usd'] as $column) {
            if (! Schema::hasColumn('sale_items', $column)) {
                continue;
            }

            $current = DB::selectOne(
                "SELECT NUMERIC_SCALE AS scale FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = ?",
                [$database, $column]
            );

            if ($current && (int) $current->scale < 8) {
                DB::statement("ALTER TABLE sale_items MODIFY {$column} DECIMAL(45,8) NOT NULL DEFAULT 0");
            }
        }
    }

    private function fixSaleItems(): void
    {
        if (! Schema::hasTable('sale_items') || ! Schema::hasColumn('sale_items', 'net_sell_price')) {
            return;
        }

        // Per-unit only. Totals are left untouched by design.
        DB::statement("
            UPDATE sale_items
            SET net_sell_price     = price - unit_discount_amount,
                net_sell_price_usd = price_usd - unit_discount_amount_usd
            WHERE ABS(net_sell_price - (price - unit_discount_amount)) > 0.0000001
               OR ABS(net_sell_price_usd - (price_usd - unit_discount_amount_usd)) > 0.0000001
        ");
    }

    private function fixReturnItems(): void
    {
        if (! Schema::hasTable('customer_return_items')
            || ! Schema::hasColumn('customer_return_items', 'total_taxable_amount')) {
            return;
        }

        // Full-precision taxable base per unit: prefer the exact percent when present,
        // otherwise fall back to the stored per-unit discount amount.
        $net    = '(price - (CASE WHEN discount_percent > 0 THEN price * discount_percent / 100 ELSE unit_discount_amount END))';
        $netUsd = '(price_usd - (CASE WHEN discount_percent > 0 THEN price_usd * discount_percent / 100 ELSE unit_discount_amount_usd END))';

        $softDelete = Schema::hasColumn('customer_return_items', 'deleted_at')
            ? 'deleted_at IS NULL AND'
            : '';

        DB::statement("
            UPDATE customer_return_items
            SET unit_taxable_amount      = {$net},
                unit_taxable_amount_usd  = {$netUsd},
                total_taxable_amount     = quantity * {$net},
                total_taxable_amount_usd = quantity * {$netUsd},
                total_tax_amount         = total_price - (quantity * {$net}),
                total_tax_amount_usd     = total_price_usd - (quantity * {$netUsd})
            WHERE {$softDelete} ABS(total_taxable_amount - (quantity * {$net})) > 0.005
        ");
    }

    private function fixReturnSubtotals(): void
    {
        if (! Schema::hasTable('customer_returns')
            || ! Schema::hasColumn('customer_returns', 'subtotal_taxable_amount')
            || ! Schema::hasTable('customer_return_items')) {
            return;
        }

        $itemsSoftDelete   = Schema::hasColumn('customer_return_items', 'deleted_at') ? 'WHERE deleted_at IS NULL' : '';
        $returnsSoftDelete = Schema::hasColumn('customer_returns', 'deleted_at') ? 'cr.deleted_at IS NULL AND' : '';

        // Sum ROUND(...,2) per item to match how the app aggregates (decimal:2-cast values).
        DB::statement("
            UPDATE customer_returns cr
            JOIN (
                SELECT customer_return_id,
                       SUM(ROUND(total_taxable_amount, 2))     AS st,
                       SUM(ROUND(total_taxable_amount_usd, 2)) AS stu,
                       SUM(ROUND(total_tax_amount, 2))         AS tt,
                       SUM(ROUND(total_tax_amount_usd, 2))     AS ttu
                FROM customer_return_items
                {$itemsSoftDelete}
                GROUP BY customer_return_id
            ) agg ON agg.customer_return_id = cr.id
            SET cr.subtotal_taxable_amount     = agg.st,
                cr.subtotal_taxable_amount_usd = agg.stu,
                cr.total_tax_amount            = agg.tt,
                cr.total_tax_amount_usd        = agg.ttu
            WHERE {$returnsSoftDelete} ABS(cr.subtotal_taxable_amount - agg.st) > 0.005
        ");
    }

    /**
     * No-op: this is a data-repair migration. The prior (rounded) values are not recoverable,
     * and reversing would only re-introduce the bug.
     */
    public function down(): void
    {
    }
};
