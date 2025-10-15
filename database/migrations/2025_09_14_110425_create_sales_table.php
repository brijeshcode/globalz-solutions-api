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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // auto generated invoice code
            $table->datetime('date');
            $table->datetime('value_date')->nullable()->comment('date by payment term');
            $table->enum('prefix', ['INX', 'INV'])->default('INV');
            $table->enum('status', ['Waiting', 'Shipped', 'Delivered'])->nullable(); // null untill its been approved


            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();

            $table->unsignedBigInteger('customer_payment_term_id')->nullable()->index();
            $table->unsignedBigInteger('customer_last_payment_receipt_id')->nullable()->index();
            
            $table->string('client_po_number')->nullable();
            $table->rate('currency_rate')->default(0);


            $table->money('credit_limit')->default(0);
            $table->money('outStanding_balance')->default(0)->comment('this is the current balance of customer at the time of sales');

            $table->money('sub_total')->default(0);
            $table->money('sub_total_usd')->default(0);
            
            $table->money('discount_amount')->default(0); // additional manual discount_amount 
            $table->money('discount_amount_usd')->default(0);

            $table->money('total')->default(0);
            $table->money('total_usd')->default(0)->comment('sub_total_usd - discount_amount_usd');

            $table->money('total_profit')->default(0);

            $table->decimal('total_volume_cbm', 10,4)->default(0);
            $table->decimal('total_weight_kg', 10,4)->default(0);

            $table->money('total_tax_amount')->default(0); // sum of all tax amount
            $table->money('total_tax_amount_usd')->default(0);

            $table->rate('local_curreny_rate')->default(0); // country local currency rate here it is LL
            $table->string('invoice_tax_label', 200)->default('TVA 11%');
            $table->string('invoice_nb1', 200)->default('Payment in USD or Market Price.');
            $table->string('invoice_nb2', 200)->default('ملحظة : ألضريبة على ألقيمة المضافة ل تسترد بعد ثلثة أشهر من تاريخ إصدار ألفاتورة');

            $table->text('note')->nullable();  // for remark
            
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->text('approve_note')->nullable();
                
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
        Schema::dropIfExists('sales');
    }
};
