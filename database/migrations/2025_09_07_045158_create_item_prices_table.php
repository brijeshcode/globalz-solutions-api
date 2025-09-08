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
        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('item_id')->unique()->index();
            
            $table->money('price_usd', 15, 4);
            $table->date('effective_date'); // purchase date
            
            $table->unsignedBigInteger('last_purchase_id')->index();
            
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_prices');
    }
};
