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
        Schema::create('item_price_histories', function (Blueprint $table) {
            $table->id();
            // we will add histroy price if price has changed 
            $table->unsignedBigInteger('item_id')->index(); 
            
            $table->money('price_usd', 15, 4);
            $table->money('average_waited_price')->nullable()->comment('for information');
            $table->money('latest_price')->nullable()->comment('for information');
            $table->date('effective_date');
            
            // Track transaction source
            $table->string('source_type')->nullable()->comment('purchase, adjustment, manual, initial, etc.');
            $table->unsignedBigInteger('source_id')->nullable()->comment('ID of the source transaction');
            $table->text('note')->nullable()->comment('reason for price change');
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['effective_date']);
            $table->index(['item_id', 'effective_date']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_price_histories');
    }
};
