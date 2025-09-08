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
        Schema::create('supplier_item_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();

            $table->money('price')->default(0);
            $table->money('price_usd')->default(0); // converted price for comparison
            $table->rate('currency_rate')->default(1);


            $table->unsignedBigInteger('last_purchase_id')->nullable()->index();
            $table->date('last_purchase_date')->nullable();
            // $table->quantity('last_purchase_quantity')->nullable();


            $table->boolean('is_current')->default(true)->comment('only one true per suppier_id , item_id'); // use to fetch last price for supplier item
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['supplier_id', 'item_id', 'is_current'], 'idx_supplier_item_current');
            $table->index(['item_id', 'price_usd', 'is_current'], 'idx_item_price_current'); // for finding best current prices
            $table->index(['supplier_id', 'item_id', 'created_at'], 'idx_supplier_item_history');
            $table->index('last_purchase_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_item_prices');
    }
};
