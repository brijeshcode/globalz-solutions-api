<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            // Taxable base = unit/line price after discount, before tax (mirrors sale_items.net_sell_price)
            $table->money('unit_taxable_amount')->default(0)->after('ttc_price_usd')
                ->comment('unit price after discount, before tax');
            $table->money('unit_taxable_amount_usd')->default(0)->after('unit_taxable_amount')
                ->comment('unit_taxable_amount in usd');
            $table->money('total_taxable_amount')->default(0)->after('unit_taxable_amount_usd')
                ->comment('unit_taxable_amount * quantity');
            $table->money('total_taxable_amount_usd')->default(0)->after('total_taxable_amount')
                ->comment('total_taxable_amount in usd');
            $table->money('total_tax_amount')->default(0)->after('total_taxable_amount_usd')
                ->comment('total_price - total_taxable_amount (0 for tax-free RTX)');
            $table->money('total_tax_amount_usd')->default(0)->after('total_tax_amount')
                ->comment('total_tax_amount in usd');
        });

        $this->backfill();
    }

    /**
     * Backfill existing rows. Sale-linked rows take the taxable base from their sale item;
     * direct returns (no sale_item_id) derive it from their own price/discount.
     * Portable across drivers; no-op on empty tables (e.g. fresh test DB).
     */
    private function backfill(): void
    {
        DB::table('customer_return_items')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $qty = (float) $row->quantity;

                    if ($row->sale_item_id) {
                        // Sale-linked return: taxable base = sale item's net sell price
                        $saleItem = DB::table('sale_items')->find($row->sale_item_id);
                        if (!$saleItem) {
                            continue; // orphaned link; leave defaults (0)
                        }
                        $unit    = (float) $saleItem->net_sell_price;
                        $unitUsd = (float) $saleItem->net_sell_price_usd;
                    } else {
                        // Direct return: derive taxable base from the row's own price/discount
                        $discountPercent = (float) $row->discount_percent;
                        $unitDiscount    = $discountPercent > 0 ? (float) $row->price * ($discountPercent / 100) : (float) $row->unit_discount_amount;
                        $unitDiscountUsd = $discountPercent > 0 ? (float) $row->price_usd * ($discountPercent / 100) : (float) $row->unit_discount_amount_usd;
                        $unit    = (float) $row->price - $unitDiscount;
                        $unitUsd = (float) $row->price_usd - $unitDiscountUsd;
                    }

                    $totalTaxable    = $unit * $qty;
                    $totalTaxableUsd = $unitUsd * $qty;

                    DB::table('customer_return_items')->where('id', $row->id)->update([
                        'unit_taxable_amount'      => $unit,
                        'unit_taxable_amount_usd'  => $unitUsd,
                        'total_taxable_amount'     => $totalTaxable,
                        'total_taxable_amount_usd' => $totalTaxableUsd,
                        'total_tax_amount'         => (float) $row->total_price - $totalTaxable,
                        'total_tax_amount_usd'     => (float) $row->total_price_usd - $totalTaxableUsd,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_taxable_amount',
                'unit_taxable_amount_usd',
                'total_taxable_amount',
                'total_taxable_amount_usd',
                'total_tax_amount',
                'total_tax_amount_usd',
            ]);
        });
    }
};
