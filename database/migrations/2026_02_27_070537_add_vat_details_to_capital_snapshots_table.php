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
        Schema::table('capital_snapshots', function (Blueprint $table) {
            $table->decimal('tax_free_purchases_total', 15, 2)->default(0)->after('available_stock_value');
            $table->decimal('total_vat_on_stock', 15, 2)->default(0)->after('tax_free_purchases_total');
            $table->decimal('vat_paid_on_purchase', 15, 2)->default(0)->after('total_vat_on_stock');
            $table->decimal('default_tax_percent', 5, 2)->default(0)->after('vat_paid_on_purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_snapshots', function (Blueprint $table) {
            $table->dropColumn(['tax_free_purchases_total', 'total_vat_on_stock', 'vat_paid_on_purchase', 'default_tax_percent']);
        });
    }
};
