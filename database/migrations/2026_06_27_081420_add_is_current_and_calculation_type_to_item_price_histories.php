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
        Schema::table('item_price_histories', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('note')
                ->comment('Indicates this row currently drives item_prices for this item');
            $table->string('calculation_type', 50)->nullable()->after('is_current')
                ->comment('Cost calculation method at time of recording: weighted_average or last_cost');
        });
    }

    public function down(): void
    {
        Schema::table('item_price_histories', function (Blueprint $table) {
            $table->dropColumn(['is_current', 'calculation_type']);
        });
    }
};
