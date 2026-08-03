<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->datetime('date');
            $table->datetime('value_date')->nullable();
            $table->string('prefix')->default('PINV');
            $table->string('status')->default('Draft');

            $table->unsignedBigInteger('salesperson_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->unsignedBigInteger('price_list_id')->nullable()->index();
            $table->unsignedBigInteger('customer_payment_term_id')->nullable()->index();

            $table->string('client_po_number')->nullable();
            $table->rate('currency_rate')->default(0);

            $table->money('sub_total')->default(0);
            $table->money('sub_total_usd')->default(0);
            $table->money('discount_amount')->default(0);
            $table->money('discount_amount_usd')->default(0);
            $table->money('total')->default(0);
            $table->money('total_usd')->default(0);
            $table->money('total_profit')->default(0);

            $table->decimal('total_volume_cbm', 10, 4)->default(0);
            $table->decimal('total_weight_kg', 10, 4)->default(0);

            $table->money('total_tax_amount')->default(0);
            $table->money('total_tax_amount_usd')->default(0);

            $table->rate('local_curreny_rate')->default(0);
            $table->string('invoice_tax_label', 200)->default('TVA 11%');
            $table->string('invoice_nb1', 200)->default('Payment in USD or Market Price.');
            $table->string('invoice_nb2', 200)->default('ملحظة : ألضريبة على ألقيمة المضافة ل تسترد بعد ثلثة أشهر من تاريخ إصدار ألفاتورة');

            $table->text('note')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('approved_at')->nullable();
            $table->text('approve_note')->nullable();

            $table->dateTime('converted_at')->nullable();
            $table->foreignId('converted_sale_id')->nullable()->constrained('sales')->onDelete('set null');

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
