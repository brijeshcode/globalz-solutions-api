<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->decimal('vat_amount', 15, 2)->default(0)->after('amount_usd');
            $table->decimal('vat_amount_usd', 15, 8)->default(0)->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->dropColumn(['vat_amount', 'vat_amount_usd']);
        });
    }
};
