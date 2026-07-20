<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');

            $table->unsignedBigInteger('proforma_invoice_id')->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();

            $table->money('cost_price')->default(0)->comment('in usd');
            $table->money('price')->default(0);
            $table->money('price_usd')->default(0);

            $table->percent('discount_percent')->default(0);
            $table->money('unit_discount_amount')->default(0);
            $table->money('unit_discount_amount_usd')->default(0);

            $table->money('net_sell_price')->default(0);
            $table->money('net_sell_price_usd')->default(0);

            $table->quantity('quantity')->default(0);

            $table->money('tax_percent')->default(0);
            $table->string('tax_label')->default('TVA');

            $table->money('tax_amount')->default(0);
            $table->money('tax_amount_usd')->default(0);
            $table->money('total_tax_amount')->default(0);
            $table->money('total_tax_amount_usd')->default(0);

            $table->money('ttc_price')->default(0);
            $table->money('ttc_price_usd')->default(0);

            $table->money('unit_profit')->default(0);

            $table->money('total_net_sell_price')->default(0);
            $table->money('total_net_sell_price_usd')->default(0);

            $table->money('total_price')->default(0);
            $table->money('total_price_usd')->default(0);

            $table->money('discount_amount')->default(0);
            $table->money('discount_amount_usd')->default(0);

            $table->money('total_profit')->default(0);

            $table->decimal('unit_volume_cbm', 10, 4)->default(0);
            $table->decimal('unit_weight_kg', 10, 4)->default(0);
            $table->decimal('total_volume_cbm', 10, 4)->default(0);
            $table->decimal('total_weight_kg', 10, 4)->default(0);

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_items');
    }
};
