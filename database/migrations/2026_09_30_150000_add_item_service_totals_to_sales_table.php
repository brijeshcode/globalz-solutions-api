<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Break out item vs service totals; grand `total`/`total_profit` stay combined.
            $table->money('items_total')->default(0)->after('total_profit')->comment('sum of item line total_price (ttc), selected currency');
            $table->money('items_total_usd')->default(0)->after('items_total');
            $table->money('items_profit')->default(0)->after('items_total_usd')->comment('sum of item total_profit, usd');

            $table->money('services_total')->default(0)->after('items_profit')->comment('sum of service line total_price (ttc), selected currency');
            $table->money('services_total_usd')->default(0)->after('services_total');
            $table->money('services_profit')->default(0)->after('services_total_usd')->comment('sum of service total_profit, usd');

            // Per-type tax breakout; grand `total_tax_amount` stays combined.
            $table->money('items_total_tax_amount')->default(0)->after('services_profit')->comment('sum of item line total_tax_amount, selected currency');
            $table->money('items_total_tax_amount_usd')->default(0)->after('items_total_tax_amount');
            $table->money('services_total_tax_amount')->default(0)->after('items_total_tax_amount_usd')->comment('sum of service line total_tax_amount, selected currency');
            $table->money('services_total_tax_amount_usd')->default(0)->after('services_total_tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'items_total',
                'items_total_usd',
                'items_profit',
                'services_total',
                'services_total_usd',
                'services_profit',
                'items_total_tax_amount',
                'items_total_tax_amount_usd',
                'services_total_tax_amount',
                'services_total_tax_amount_usd',
            ]);
        });
    }
};
