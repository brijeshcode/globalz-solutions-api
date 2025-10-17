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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // auto generated invoice code
            $table->enum('prefix', ['PUR', 'PAX'])->default('PUR');
            $table->enum('status', ['Waiting', 'Shipped', 'Delivered'])->default('Waiting'); // null untill its been approved
            $table->datetime('date');

            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('supplier_invoice_number')->nullable();

            $table->rate('currency_rate')->default(0);

            $table->money('shipping_fee_usd')->default(0);
            $table->money('customs_fee_usd')->default(0);
            $table->money('other_fee_usd')->default(0);
            $table->money('tax_usd')->default(0);

            $table->percent('shipping_fee_usd_percent')->default(0);
            $table->percent('customs_fee_usd_percent')->default(0);
            $table->percent('other_fee_usd_percent')->default(0);
            $table->percent('tax_usd_percent')->default(0);

            $table->money('sub_total')->default(0);
            $table->money('sub_total_usd')->default(0);
            
            $table->money('discount_amount')->default(0); // additional manual discount_amount 
            $table->money('discount_amount_usd')->default(0);

            $table->money('total')->default(0);
            $table->money('total_usd')->default(0)->comment('sub_total_usd - discount_amount_usd');

            $table->money('final_total')->default(0);
            $table->money('final_total_usd')->default(0)->comment('total_usd + shipping_usd + custom_usd + other_usd');

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
        Schema::dropIfExists('purchases');
    }
};
