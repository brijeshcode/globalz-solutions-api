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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code'); // comes form items table

            $table->unsignedBigInteger('sale_id')->index(); // Foreign key to sales table
            $table->unsignedBigInteger('item_id')->nullable()->index();

            $table->quantity('quantity')->default(0);
            $table->money('cost_price')->default(0)->comment('in usd');
            
            $table->decimal('unit_volume_cbm', 10,4)->default(0);
            $table->decimal('unit_weight_kg', 10,4)->default(0);
            
            $table->money('unit_profit')->default(0)->comment('ttc_price_usd - cost_price');

            $table->money('price')->default(0)->comment('sale price is in selected currency');
            $table->money('price_usd')->default(0)->comment('sale price_usd use for profit calculation');

            $table->money('tax_percent')->default(0)->comment('tax percent from the items table');
            $table->string('tax_label')->default('TVA')->comment('can be TVA and No');
            $table->money('tax_amount')->default(0)->comment('tax amount per unit ');
            $table->money('tax_amount_usd')->default(0)->comment('tax amount usd per unit ');

            $table->money('ttc_price')->default(0)->comment('ttc price will be in selected currency');
            $table->money('ttc_price_usd')->default(0)->comment('ttc price usd');
            
            $table->percent('discount_percent')->default(0);
            
            $table->money('unit_discount_amount')->default(0)->comment('in selected_currency');
            $table->money('unit_discount_amount_usd')->default(0)->comment('in usd');
            
            $table->money('discount_amount')->default(0)->comment('in usd');
            $table->money('discount_amount_usd')->default(0)->comment('in selected_currency');
            
            $table->money('total_price')->default(0); // price - discount * quantity
            $table->money('total_price_usd')->default(0);
            $table->money('total_profit')->default(0)->comment('total_price_usd - (cost_price * quantity)');

            $table->decimal('total_volume_cbm', 10,4)->default(0);
            $table->decimal('total_weight_kg', 10,4)->default(0);

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
        Schema::dropIfExists('sale_items');
    }
};
