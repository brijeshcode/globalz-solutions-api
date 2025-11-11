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
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            
            $table->string('item_code'); // comes form items table

            $table->unsignedBigInteger('price_list_id')->index(); // Foreign key to sales table
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->text('item_description')->nullable()->comment('name of item which is desctiption in this system');

            $table->money('sell_price')->default(0)->comment('in usd');


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
        Schema::dropIfExists('price_list_items');
    }
};
