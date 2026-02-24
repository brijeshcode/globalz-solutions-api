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
        Schema::create('price_list_bulk_update_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bulk_update_id')->index();
            $table->unsignedBigInteger('price_list_item_id')->nullable()->index();
            $table->unsignedBigInteger('price_list_id')->nullable()->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('item_code');
            $table->text('item_description')->nullable();
            $table->money('old_price')->default(0);
            $table->money('new_price')->default(0);

            $table->timestamps();

            $table->foreign('bulk_update_id')->references('id')->on('price_list_bulk_updates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_list_bulk_update_items');
    }
};
