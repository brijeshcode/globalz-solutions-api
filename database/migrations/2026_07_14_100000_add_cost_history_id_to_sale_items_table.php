<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which item_price_histories row supplied the cost_price on each
     * sale item — provenance for profit audits and an exact skip check for
     * the recalculation walk. Nullable: rows written before this feature (or
     * for items without purchase history) simply have no provenance yet.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_history_id')->nullable()->after('cost_price')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['cost_history_id']);
            $table->dropColumn('cost_history_id');
        });
    }
};
