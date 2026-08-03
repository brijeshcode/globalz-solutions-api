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
        Schema::table('item_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('price_history_id')->nullable()->after('effective_date')
                ->comment('References the item_price_histories row that set this current price');
        });
    }

    public function down(): void
    {
        Schema::table('item_prices', function (Blueprint $table) {
            $table->dropColumn('price_history_id');
        });
    }
};
