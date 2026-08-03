<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_fee_usd',
                'customs_fee_usd',
                'other_fee_usd',
                'tax_usd',
                'shipping_fee_usd_percent',
                'customs_fee_usd_percent',
                'other_fee_usd_percent',
                'tax_usd_percent',
            ]);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn([
                'total_shipping_usd',
                'total_customs_usd',
                'total_other_usd',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('shipping_fee_usd', 12, 4)->default(0);
            $table->decimal('customs_fee_usd', 12, 4)->default(0);
            $table->decimal('other_fee_usd', 12, 4)->default(0);
            $table->decimal('tax_usd', 12, 4)->default(0);
            $table->decimal('shipping_fee_usd_percent', 5, 2)->default(0);
            $table->decimal('customs_fee_usd_percent', 5, 2)->default(0);
            $table->decimal('other_fee_usd_percent', 5, 2)->default(0);
            $table->decimal('tax_usd_percent', 5, 2)->default(0);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('total_shipping_usd', 12, 2)->default(0);
            $table->decimal('total_customs_usd', 12, 2)->default(0);
            $table->decimal('total_other_usd', 12, 2)->default(0);
        });
    }
};
