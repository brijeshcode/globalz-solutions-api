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
        Schema::create('customer_return_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code'); // comes form items table

            $table->unsignedBigInteger('customer_return_id')->index(); // Foreign key to customer returns table
            $table->unsignedBigInteger('item_id')->nullable()->index();

            $table->quantity('quantity')->default(0);

            $table->money('price')->default(0)->comment('sale price always in usd');
            
            $table->percent('discount_percent')->default(0);
            $table->money('unit_discount_amount')->default(0);
            $table->money('discount_amount')->default(0);
            
            $table->money('tax_percent')->default(0)->comment('tax percent from the items table');
            $table->money('ttc_price')->default(0)->comment('sale price always in usd');

            $table->money('total_price')->default(0); // price - discount * quantity
            $table->money('total_price_usd')->default(0);
            
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
        Schema::dropIfExists('customer_return_items');
    }
};
