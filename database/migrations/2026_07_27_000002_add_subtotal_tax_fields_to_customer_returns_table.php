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
        Schema::table('customer_returns', function (Blueprint $table) {
            // Net-of-tax subtotal + tax total; `total` (existing) remains the tax-inclusive grand total.
            $table->money('subtotal_taxable_amount')->default(0)->after('total_usd')
                ->comment('sum of items.total_taxable_amount (net of tax)');
            $table->money('subtotal_taxable_amount_usd')->default(0)->after('subtotal_taxable_amount')
                ->comment('subtotal_taxable_amount in usd');
            $table->money('total_tax_amount')->default(0)->after('subtotal_taxable_amount_usd')
                ->comment('sum of items.total_tax_amount (0 for tax-free RTX)');
            $table->money('total_tax_amount_usd')->default(0)->after('total_tax_amount')
                ->comment('total_tax_amount in usd');
        });

        $this->backfill();
    }

    /**
     * Backfill parent totals from the (already backfilled) return items.
     * Portable across drivers; no-op on empty tables (e.g. fresh test DB).
     */
    private function backfill(): void
    {
        DB::table('customer_returns')
            ->orderBy('id')
            ->chunkById(500, function ($returns) {
                foreach ($returns as $return) {
                    $totals = DB::table('customer_return_items')
                        ->where('customer_return_id', $return->id)
                        ->whereNull('deleted_at')
                        ->selectRaw('
                            COALESCE(SUM(total_taxable_amount), 0) as subtotal,
                            COALESCE(SUM(total_taxable_amount_usd), 0) as subtotal_usd,
                            COALESCE(SUM(total_tax_amount), 0) as tax,
                            COALESCE(SUM(total_tax_amount_usd), 0) as tax_usd
                        ')
                        ->first();

                    DB::table('customer_returns')->where('id', $return->id)->update([
                        'subtotal_taxable_amount'     => $totals->subtotal,
                        'subtotal_taxable_amount_usd' => $totals->subtotal_usd,
                        'total_tax_amount'            => $totals->tax,
                        'total_tax_amount_usd'        => $totals->tax_usd,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_taxable_amount',
                'subtotal_taxable_amount_usd',
                'total_tax_amount',
                'total_tax_amount_usd',
            ]);
        });
    }
};
