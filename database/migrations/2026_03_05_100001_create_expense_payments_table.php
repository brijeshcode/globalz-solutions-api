<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expense_transaction_id')->constrained('expense_transactions')->onDelete('restrict');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');

            $table->decimal('amount', 15, 2);
            $table->decimal('amount_usd', 15, 8)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onDelete('restrict');
            $table->decimal('currency_rate', 10, 4)->nullable();

            $table->enum('prefix', ['EP', 'EPX'])->default('EP');
            $table->datetime('date');
            $table->string('note', 250)->nullable();

            // Payment reference fields (optional, same as on the expense)
            $table->string('order_number', 100)->nullable();
            $table->string('check_number', 100)->nullable();
            $table->string('bank_ref_number', 100)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_transaction_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
