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
        Schema::create('expense_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('code', 200)->unique()->index(); // Auto-generated starting from 100

            $table->foreignId('expense_category_id')->constrained('expense_categories')->onDelete('restrict');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->string('subject', 200)->nullable();
            $table->decimal('amount', 15, 2);
            
            $table->string('order_number', 100)->nullable();
            $table->string('check_number', 100)->nullable();
            $table->string('bank_ref_number', 100)->nullable();
            $table->string('note', 250)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'expense_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_transactions');
    }
};
