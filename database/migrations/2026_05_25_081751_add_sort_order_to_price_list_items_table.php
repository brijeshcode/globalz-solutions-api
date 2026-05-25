<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('sell_price');
        });

        // Seed existing rows: assign sort_order per price_list_id based on id order
        DB::statement('
            UPDATE price_list_items pli
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY price_list_id ORDER BY id) AS rn
                FROM price_list_items
                WHERE deleted_at IS NULL
            ) ranked ON pli.id = ranked.id
            SET pli.sort_order = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
