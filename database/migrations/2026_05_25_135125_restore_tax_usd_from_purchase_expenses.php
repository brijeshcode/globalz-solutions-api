<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $taxCategoryId = DB::table('expense_categories')
            ->where('name', 'Tax')
            ->whereNotNull('parent_id')
            ->value('id');

        if (!$taxCategoryId) {
            return;
        }

        $rows = DB::table('purchase_expenses as pe')
            ->join('expense_transactions as et', 'et.id', '=', 'pe.expense_transaction_id')
            ->where('et.expense_category_id', $taxCategoryId)
            ->whereNull('et.deleted_at')
            ->select('pe.id as purchase_expense_id', 'pe.purchase_id', 'et.id as expense_transaction_id', 'et.amount_usd')
            ->get();

        foreach ($rows as $row) {
            // Restore tax_usd on the purchase
            DB::table('purchases')
                ->where('id', $row->purchase_id)
                ->update(['tax_usd' => $row->amount_usd]);

            // Remove the purchase_expense link
            DB::table('purchase_expenses')
                ->where('id', $row->purchase_expense_id)
                ->delete();

            // Remove any payments on the expense_transaction first (FK constraint)
            DB::table('expense_payments')
                ->where('expense_transaction_id', $row->expense_transaction_id)
                ->delete();

            // Remove the expense_transaction
            DB::table('expense_transactions')
                ->where('id', $row->expense_transaction_id)
                ->delete();
        }
    }

    public function down(): void
    {
        DB::table('purchases')->update(['tax_usd' => 0]);
    }
};
