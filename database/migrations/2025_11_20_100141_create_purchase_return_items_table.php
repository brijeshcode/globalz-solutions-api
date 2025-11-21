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
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id')->index();
            
            $table->string('item_code'); // comes form items table 
            $table->unsignedBigInteger('item_id')->index();

            $table->money('price')->default(0);
            $table->quantity('quantity')->default(0);
            $table->percent('discount_percent')->default(0);
            $table->money('unit_discount_amount')->default(0);
            $table->money('discount_amount')->default(0);
            $table->money('total_price')->default(0); // price - discount * quantity

            $table->money('total_price_usd')->default(0);
            $table->money('total_shipping_usd')->default(0);
            $table->money('total_customs_usd')->default(0);
            $table->money('total_other_usd')->default(0);
            $table->money('final_total_cost_usd')->default(0);
            $table->money('cost_per_item_usd')->default(0);

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
        Schema::dropIfExists('purchase_return_items');
    }
};
