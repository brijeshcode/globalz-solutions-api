<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_refills', function (Blueprint $table) {
            $table->decimal('sales_amount_usd', 15, 8)->nullable()->after('invoices_count');
            $table->decimal('delivery_cost_pct', 8, 4)->nullable()->after('sales_amount_usd');
        });
    }

    public function down(): void
    {
        Schema::table('car_refills', function (Blueprint $table) {
            $table->dropColumn(['sales_amount_usd', 'delivery_cost_pct']);
        });
    }
};
