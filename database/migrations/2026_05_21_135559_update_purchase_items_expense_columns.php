<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('total_expense_usd', 12, 2)->default(0)->after('total_price_usd');
        });
        // Old columns (total_shipping_usd, total_customs_usd, total_other_usd)
        // are dropped AFTER data migration (Task 17)
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('total_expense_usd');
        });
    }
};
