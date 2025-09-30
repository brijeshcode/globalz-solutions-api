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
        Schema::create('customer_balance_monthlies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->year('year'); 
            $table->tinyInteger('month')->unsigned()->comment('1=Jan, 12=Dec');

            $table->integer('total_sale')->default(0);
            $table->decimal('total_sale_amount')->default(0);

            $table->integer('total_return')->default(0);
            $table->decimal('total_return_amount')->default(0);

            $table->integer('total_credit')->default(0);
            $table->decimal('total_credit_amount')->default(0);

            $table->integer('total_debit')->default(0);
            $table->decimal('total_debit_amount')->default(0);

            $table->integer('total_payment')->default(0);
            $table->decimal('total_payment_amount')->default(0);
            
            $table->decimal('transaction_total', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);

            $table->enum('last_updated_by', ['credit', 'debit', 'sale', 'payment', 'return'])->default('credit')->nullable()->comment('this is for current month only');
            $table->unsignedBigInteger('updated_by_entry_id')->nullable();

            $table->timestamps();

            $table->unique(['customer_id', 'year', 'month'], 'unique_customer_month_balance');
            $table->index(['customer_id', 'year'], 'idx_customer_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_balance_monthlies');
    }
};
