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
        Schema::table('customer_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->change();
            $table->unsignedBigInteger('sale_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            $table->unsignedBigInteger('sale_item_id')->nullable(false)->change();
        });
    }
};
