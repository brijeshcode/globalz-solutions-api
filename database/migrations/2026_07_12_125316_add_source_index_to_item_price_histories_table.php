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
            $table->index(['source_type', 'source_id'], 'iph_source_type_source_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_price_histories', function (Blueprint $table) {
            $table->dropIndex('iph_source_type_source_id_index');
        });
    }
};
