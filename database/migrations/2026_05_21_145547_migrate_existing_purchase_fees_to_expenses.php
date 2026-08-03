<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = ExpenseCategory::firstOrCreate(
            ['name' => 'Purchase Expenses'],
            ['is_active' => true, 'is_system' => true]
        );

        $categoryMap = [
            'shipping_fee_usd' => ExpenseCategory::firstOrCreate(
                ['name' => 'Shipping', 'parent_id' => $parent->id],
                ['is_active' => true, 'parent_id' => $parent->id]
            )->id,
            'customs_fee_usd' => ExpenseCategory::firstOrCreate(
                ['name' => 'Customs', 'parent_id' => $parent->id],
                ['is_active' => true, 'parent_id' => $parent->id]
            )->id,
            'other_fee_usd' => ExpenseCategory::firstOrCreate(
                ['name' => 'Other', 'parent_id' => $parent->id],
                ['is_active' => true, 'parent_id' => $parent->id]
            )->id,
            'tax_usd' => ExpenseCategory::firstOrCreate(
                ['name' => 'Tax', 'parent_id' => $parent->id],
                ['is_active' => true, 'parent_id' => $parent->id]
            )->id,
        ];

        Purchase::withTrashed()
            ->chunk(100, function ($purchases) use ($categoryMap) {
                foreach ($purchases as $purchase) {
                    foreach ($categoryMap as $feeColumn => $categoryId) {
                        $amountUsd = (float) ($purchase->{$feeColumn} ?? 0);
                        if ($amountUsd <= 0) {
                            continue;
                        }

                        $category = ExpenseCategory::find($categoryId);
                        $subject  = "{$category->name} — Purchase {$purchase->prefix}{$purchase->code}";

                        $expenseTx = ExpenseTransaction::create([
                            'date'                => $purchase->date,
                            'expense_month'       => $purchase->date->format('Y-m'),
                            'expense_category_id' => $categoryId,
                            'account_id'          => null,
                            'subject'             => $subject,
                            'note'                => $subject,
                            'amount'              => $amountUsd,
                            'amount_usd'          => $amountUsd,
                            'currency_id'         => $purchase->currency_id,
                            'currency_rate'       => $purchase->currency_rate,
                            'paid_amount'         => 0,
                            'paid_amount_usd'     => 0,
                            'vat_amount'          => 0,
                            'vat_amount_usd'      => 0,
                        ]);

                        PurchaseExpense::create([
                            'purchase_id'            => $purchase->id,
                            'expense_transaction_id' => $expenseTx->id,
                            'exclude_from_item_cost' => false,
                        ]);
                    }

                    // Migrate purchase_items: sum the 3 old fee columns into total_expense_usd
                    $purchase->purchaseItems()->withTrashed()->each(function ($item) {
                        $totalExpenseUsd = (float) ($item->total_shipping_usd ?? 0)
                            + (float) ($item->total_customs_usd ?? 0)
                            + (float) ($item->total_other_usd ?? 0);

                        DB::table('purchase_items')
                            ->where('id', $item->id)
                            ->update(['total_expense_usd' => $totalExpenseUsd]);
                    });

                    // Recalculate purchase total_expense_usd
                    $totalExpenseUsd = PurchaseExpense::where('purchase_id', $purchase->id)
                        ->join('expense_transactions', 'purchase_expenses.expense_transaction_id', '=', 'expense_transactions.id')
                        ->whereNull('expense_transactions.deleted_at')
                        ->sum('expense_transactions.amount_usd');

                    DB::table('purchases')
                        ->where('id', $purchase->id)
                        ->update(['total_expense_usd' => $totalExpenseUsd]);
                }
            });
    }

    public function down(): void
    {
        // Cannot reverse — restore from backup if needed.
    }
};
