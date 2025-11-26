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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            
            $table->enum('prefix', ['SPAY'])->default('SPAY');
            $table->string('code', 50)->unique()->comment('rct_number');
            
            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('supplier_payment_term_id')->nullable();
            $table->unsignedBigInteger('account_id')->index();
            
            $table->unsignedBigInteger('currency_id')->index();
            $table->rate('currency_rate')->default(1);

            $table->money('amount')->default(0);
            $table->money('amount_usd')->default(0);
            $table->money('last_payment_amount_usd')->default(0)->comment('this is last payment done to this supplier, always usd');
            
            $table->string('supplier_order_number')->nullable()->comment('manualy added by user');
            $table->string('check_number', 100)->nullable();
            $table->string('bank_ref_number', 100)->nullable();
            $table->text('note')->nullable(); // for remark 

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
