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
        Schema::create('capital_snapshots', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->tinyInteger('month')->unsigned()->comment('1=Jan, 12=Dec');

            $table->decimal('available_stock_value', 15, 2)->default(0);
            $table->decimal('vat_on_current_stock', 15, 2)->default(0);
            $table->decimal('pending_purchases_value', 15, 2)->default(0);
            $table->decimal('net_stock_value', 15, 2)->default(0);

            $table->decimal('unpaid_customer_balance', 15, 2)->default(0);
            $table->decimal('unapproved_payments', 15, 2)->default(0);

            $table->decimal('accounts_balance', 15, 2)->default(0);

            $table->decimal('net_capital', 15, 2)->default(0);
            $table->decimal('debt_account', 15, 2)->default(0);
            $table->decimal('final_result', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['year', 'month'], 'unique_capital_snapshot_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capital_snapshots');
    }
};
