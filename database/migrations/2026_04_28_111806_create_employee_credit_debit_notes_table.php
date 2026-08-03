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
        Schema::create('employee_credit_debit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->datetime('date');
            $table->enum('prefix', ['ECRX', 'ECRN', 'EDBX', 'EDBN'])->default('ECRN');
            $table->enum('type', ['credit', 'debit'])->default('credit');

            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->rate('currency_rate')->default(0);

            $table->money('amount')->default(0);
            $table->money('amount_usd')->default(0);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_credit_debit_notes');
    }
};
