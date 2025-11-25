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
        Schema::create('supplier_credit_debit_notes', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique(); // auto generated invoice code
            $table->datetime('date');
            $table->enum('prefix', ['SCRN', 'SDRN'])->default('SCRN');
            $table->enum('type', ['credit', 'debit'])->default('credit');

            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->rate('currency_rate')->default(0);

            $table->money('amount')->default(0);
            $table->money('amount_usd')->default(0);
            $table->text('note')->nullable();  // for remark

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
        Schema::dropIfExists('supplier_credit_debit_notes');
    }
};
