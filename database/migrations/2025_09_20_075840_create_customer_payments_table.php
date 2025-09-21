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
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->datetime('date');
            $table->enum('prefix', ['RCT', 'RCX'])->default('RCT');
            
            // auto generated payment order code 
            $table->string('code')->unique()->comment('rct_number');
            
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('customer_payment_term_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();

            $table->rate('currency_rate')->default(0);
            $table->money('amount')->default(0);
            $table->money('amount_usd')->default(0);
            $table->money('credit_limit')->default(0);
            $table->money('last_payment_amount')->default(0)->comment('this is last payment done by this customer');
            $table->string('rtc_book_number')->unique()->comment('manualy added by user');
            $table->text('note')->nullable(); // for remark 


            // Approval fields
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->text('approve_note')->nullable();
            
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
        Schema::dropIfExists('customer_payments');
    }
};
