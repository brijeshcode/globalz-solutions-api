<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix item_price_histories rows where source_type = 'purchase_item' but source_id
     * still holds a purchase_id instead of a purchase_item_id.
     *
     * Strategy: join on item_id + purchase_id to find the correct purchase_item.
     * Where multiple purchase_items exist for the same item+purchase, we take the one
     * with the lowest id (oldest), which matches how prices were originally written.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE item_price_histories iph
            INNER JOIN (
                SELECT pi.id AS purchase_item_id,
                       pi.purchase_id,
                       pi.item_id,
                       ROW_NUMBER() OVER (PARTITION BY pi.purchase_id, pi.item_id ORDER BY pi.id ASC) AS rn
                FROM purchase_items pi
                WHERE pi.deleted_at IS NULL
            ) pi_ranked ON pi_ranked.purchase_id = iph.source_id
                       AND pi_ranked.item_id     = iph.item_id
                       AND pi_ranked.rn           = 1
            SET iph.source_id = pi_ranked.purchase_item_id
            WHERE iph.source_type = 'purchase_item'
              AND iph.deleted_at  IS NULL
        ");

        // Also fix soft-deleted rows so restores work correctly
        DB::statement("
            UPDATE item_price_histories iph
            INNER JOIN (
                SELECT pi.id AS purchase_item_id,
                       pi.purchase_id,
                       pi.item_id,
                       ROW_NUMBER() OVER (PARTITION BY pi.purchase_id, pi.item_id ORDER BY pi.id ASC) AS rn
                FROM purchase_items pi
                WHERE pi.deleted_at IS NULL
            ) pi_ranked ON pi_ranked.purchase_id = iph.source_id
                       AND pi_ranked.item_id     = iph.item_id
                       AND pi_ranked.rn           = 1
            SET iph.source_id = pi_ranked.purchase_item_id
            WHERE iph.source_type = 'purchase_item'
              AND iph.deleted_at  IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Reverse: set source_id back to purchase_id
        DB::statement("
            UPDATE item_price_histories iph
            INNER JOIN purchase_items pi ON pi.id = iph.source_id
            SET iph.source_id = pi.purchase_id
            WHERE iph.source_type = 'purchase_item'
        ");
    }
};
