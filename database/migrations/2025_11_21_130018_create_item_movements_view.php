<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS item_movements_view");

        DB::statement("
            CREATE VIEW item_movements_view AS

            -- Sales (Debit - items going out)
            SELECT
                si.id,
                si.item_id,
                si.item_code,
                s.warehouse_id,
                si.sale_id as parent_id,
                CONCAT(s.prefix, s.code) as transaction_code,
                s.code as transaction_number,
                s.prefix as transaction_prefix,
                'Sale' as transaction_type,
                'sale' as transaction_type_key,
                s.date as transaction_date,
                si.quantity,
                si.quantity as debit,
                0 as credit,
                si.note,
                si.created_by,
                'sale_items' as source_table,
                UNIX_TIMESTAMP(s.date) as timestamp
            FROM sale_items si
            INNER JOIN sales s ON si.sale_id = s.id
            WHERE s.deleted_at IS NULL
                AND si.deleted_at IS NULL
               -- AND s.approved_at IS NOT NULL

            UNION ALL

            -- Purchases (Credit - items coming in)
            SELECT
                pi.id,
                pi.item_id,
                pi.item_code,
                p.warehouse_id,
                pi.purchase_id as parent_id,
                CONCAT(p.prefix, p.code) as transaction_code,
                p.code as transaction_number,
                p.prefix as transaction_prefix,
                'Purchase' as transaction_type,
                'purchase' as transaction_type_key,
                p.date as transaction_date,
                pi.quantity,
                0 as debit,
                pi.quantity as credit,
                pi.note,
                pi.created_by,
                'purchase_items' as source_table,
                UNIX_TIMESTAMP(p.date) as timestamp
            FROM purchase_items pi
            INNER JOIN purchases p ON pi.purchase_id = p.id
            WHERE p.deleted_at IS NULL
                AND pi.deleted_at IS NULL
                -- AND p.status = 'Delivered'

            UNION ALL

            -- Purchase Returns (Debit - items going out)
            SELECT
                pri.id,
                pri.item_id,
                pri.item_code,
                pr.warehouse_id,
                pri.purchase_return_id as parent_id,
                CONCAT(pr.prefix, pr.code) as transaction_code,
                pr.code as transaction_number,
                pr.prefix as transaction_prefix,
                'Purchase Return' as transaction_type,
                'purchase_return' as transaction_type_key,
                pr.date as transaction_date,
                pri.quantity,
                pri.quantity as debit,
                0 as credit,
                pri.note,
                pri.created_by,
                'purchase_return_items' as source_table,
                UNIX_TIMESTAMP(pr.date) as timestamp
            FROM purchase_return_items pri
            INNER JOIN purchase_returns pr ON pri.purchase_return_id = pr.id
            WHERE pr.deleted_at IS NULL
                AND pri.deleted_at IS NULL
              --  AND pr.shipping_status = 'Delivered'

            UNION ALL

            -- Sale Returns (Credit - items coming back in)
            SELECT
                cri.id,
                cri.item_id,
                cri.item_code,
                cr.warehouse_id,
                cri.customer_return_id as parent_id,
                CONCAT(cr.prefix, cr.code) as transaction_code,
                cr.code as transaction_number,
                cr.prefix as transaction_prefix,
                'Sale Return' as transaction_type,
                'sale_return' as transaction_type_key,
                cr.date as transaction_date,
                cri.quantity,
                0 as debit,
                cri.quantity as credit,
                cri.note,
                cri.created_by,
                'customer_return_items' as source_table,
                UNIX_TIMESTAMP(cr.date) as timestamp
            FROM customer_return_items cri
            INNER JOIN customer_returns cr ON cri.customer_return_id = cr.id
            WHERE cr.deleted_at IS NULL
                AND cri.deleted_at IS NULL
                AND cr.approved_at IS NOT NULL
                AND cr.return_received_at IS NOT NULL

            UNION ALL

            -- Transfer Out (Debit - items leaving source warehouse)
            SELECT
                iti.id,
                iti.item_id,
                iti.item_code,
                it.from_warehouse_id as warehouse_id,
                iti.item_transfer_id as parent_id,
                CONCAT(it.prefix, it.code) as transaction_code,
                it.code as transaction_number,
                it.prefix as transaction_prefix,
                'Transfer Out' as transaction_type,
                'transfer_out' as transaction_type_key,
                it.date as transaction_date,
                iti.quantity,
                iti.quantity as debit,
                0 as credit,
                iti.note,
                iti.created_by,
                'item_transfer_items' as source_table,
                UNIX_TIMESTAMP(it.date) as timestamp
            FROM item_transfer_items iti
            INNER JOIN item_transfers it ON iti.item_transfer_id = it.id
            WHERE it.deleted_at IS NULL
                AND iti.deleted_at IS NULL

            UNION ALL

            -- Transfer In (Credit - items arriving at destination warehouse)
            SELECT
                iti.id,
                iti.item_id,
                iti.item_code,
                it.to_warehouse_id as warehouse_id,
                iti.item_transfer_id as parent_id,
                CONCAT(it.prefix, it.code) as transaction_code,
                it.code as transaction_number,
                it.prefix as transaction_prefix,
                'Transfer In' as transaction_type,
                'transfer_in' as transaction_type_key,
                it.date as transaction_date,
                iti.quantity,
                0 as debit,
                iti.quantity as credit,
                iti.note,
                iti.created_by,
                'item_transfer_items' as source_table,
                UNIX_TIMESTAMP(it.date) as timestamp
            FROM item_transfer_items iti
            INNER JOIN item_transfers it ON iti.item_transfer_id = it.id
            WHERE it.deleted_at IS NULL
                AND iti.deleted_at IS NULL

            UNION ALL

            -- Adjustments (Credit for increase, Debit for decrease)
            SELECT
                iai.id,
                iai.item_id,
                iai.item_code,
                ia.warehouse_id,
                iai.item_adjust_id as parent_id,
                CONCAT(ia.prefix, ia.code) as transaction_code,
                ia.code as transaction_number,
                ia.prefix as transaction_prefix,
                CONCAT('Adjustment (', UPPER(ia.type), ')') as transaction_type,
                'adjustment' as transaction_type_key,
                ia.date as transaction_date,
                iai.quantity,
                CASE WHEN ia.type = 'add' THEN 0 ELSE iai.quantity END as debit,
                CASE WHEN ia.type = 'add' THEN iai.quantity ELSE 0 END as credit,
                iai.note,
                iai.created_by,
                'item_adjust_items' as source_table,
                UNIX_TIMESTAMP(ia.date) as timestamp
            FROM item_adjust_items iai
            INNER JOIN item_adjusts ia ON iai.item_adjust_id = ia.id
            WHERE ia.deleted_at IS NULL
                AND iai.deleted_at IS NULL

            UNION ALL

            -- Initial Inventory (from items table with starting_quantity)
            SELECT
                i.id,
                i.id as item_id,
                i.code as item_code,
                w.id as warehouse_id,
                i.id as parent_id,
                CONCAT('INIT-', i.code) as transaction_code,
                i.code as transaction_number,
                'INIT' as transaction_prefix,
                'Starting Inventory' as transaction_type,
                'initial_inventory' as transaction_type_key,
                i.created_at as transaction_date,
                i.starting_quantity as quantity,
                0 as debit,
                i.starting_quantity as credit,
                'Starting inventory from item creation' as note,
                i.created_by,
                'items' as source_table,
                UNIX_TIMESTAMP(i.created_at) as timestamp
            FROM items i
            CROSS JOIN warehouses w
            WHERE i.deleted_at IS NULL
                AND i.starting_quantity > 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS item_movements_view");
    }
};
