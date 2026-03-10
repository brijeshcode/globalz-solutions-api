<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            // Make account_id nullable — will be removed entirely once expense_payments is fully adopted
            $table->foreignId('account_id')->nullable()->change();

            // Cumulative paid tracking; payment_status is derived (0=unpaid, partial, amount==paid_amount=paid)
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('paid_amount_usd', 15, 8)->default(0)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_amount_usd']);
            $table->foreignId('account_id')->nullable(false)->change();
        });
    }
};
