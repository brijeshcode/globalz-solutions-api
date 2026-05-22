<?php

namespace App\Services\Suppliers;

use App\Models\Expenses\ExpensePayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;

class PurchaseExpenseService
{
    /**
     * Sync expense lines for a purchase.
     * Lines with 'id' are updated, lines without are created, absent lines are deleted.
     */
    public function syncExpenseLines(Purchase $purchase, array $expenses): void
    {
        $submittedIds = collect($expenses)->pluck('id')->filter()->values()->toArray();

        // Delete removed lines (restores account balance via model hooks)
        $purchase->purchaseExpenses()
            ->whereNotIn('id', $submittedIds)
            ->get()
            ->each(function (PurchaseExpense $pe) {
                $pe->expenseTransaction->delete();
                $pe->delete();
            });

        foreach ($expenses as $expenseData) {
            if (isset($expenseData['id'])) {
                $this->updateExpenseLine($purchase, $expenseData);
            } else {
                $this->createExpenseLine($purchase, $expenseData);
            }
        }
    }

    /**
     * Distribute expenses proportionally to purchase items.
     * Only expenses with exclude_from_item_cost = false are distributed.
     */
    public function recalculateItemCosts(Purchase $purchase): void
    {
        $purchase->refresh();

        $distributableUsd = (float) PurchaseExpense::where('purchase_id', $purchase->id)
            ->join('expense_transactions', 'purchase_expenses.expense_transaction_id', '=', 'expense_transactions.id')
            ->whereNull('expense_transactions.deleted_at')
            ->where('purchase_expenses.exclude_from_item_cost', false)
            ->sum('expense_transactions.amount_usd');

        $items       = $purchase->purchaseItems()->get();
        $subTotalUsd = (float) $purchase->sub_total_usd;
        $itemCount   = $items->count();

        foreach ($items as $item) {
            if ($subTotalUsd > 0) {
                $itemShare = ((float) $item->total_price_usd / $subTotalUsd) * $distributableUsd;
            } elseif ($itemCount > 0) {
                $itemShare = $distributableUsd / $itemCount;
            } else {
                $itemShare = 0;
            }

            $finalTotalCostUsd = (float) $item->total_price_usd + $itemShare;
            $costPerItemUsd    = $item->quantity > 0 ? $finalTotalCostUsd / $item->quantity : 0;

            $item->updateQuietly([
                'total_expense_usd'    => $itemShare,
                'final_total_cost_usd' => $finalTotalCostUsd,
                'cost_per_item_usd'    => $costPerItemUsd,
            ]);
        }
    }

    private function createExpenseLine(Purchase $purchase, array $data): void
    {
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $subject  = "{$category->name} — Purchase {$purchase->purchase_code}";

        $isPaid = $data['is_paid'] ?? false;

        $expenseTx = ExpenseTransaction::create([
            'date'                => $purchase->date,
            'expense_month'       => $purchase->date->format('Y-m'),
            'expense_category_id' => $data['expense_category_id'],
            'account_id'          => $isPaid ? ($data['account_id'] ?? null) : null,
            'subject'             => $subject,
            'note'                => $subject,
            'amount'              => $data['amount'],
            'amount_usd'          => $data['amount_usd'],
            'currency_id'         => $data['currency_id'],
            'currency_rate'       => $data['currency_rate'],
            'paid_amount'         => 0,
            'paid_amount_usd'     => 0,
            'vat_amount'          => 0,
            'vat_amount_usd'      => 0,
        ]);

        PurchaseExpense::create([
            'purchase_id'            => $purchase->id,
            'expense_transaction_id' => $expenseTx->id,
            'exclude_from_item_cost' => $data['exclude_from_item_cost'] ?? false,
        ]);

        if ($isPaid) {
            ExpensePayment::create([
                'expense_transaction_id' => $expenseTx->id,
                'account_id'             => $data['account_id'],
                'amount'                 => $data['amount'],
                'amount_usd'             => $data['amount_usd'],
                'currency_id'            => $data['currency_id'],
                'currency_rate'          => $data['currency_rate'],
                'date'                   => $purchase->date,
                'note'                   => $data['payment_note'] ?? null,
            ]);
            // Model boot hook fires: AccountsHelper::removeBalance + syncExpensePaidAmount
        }
    }

    private function updateExpenseLine(Purchase $purchase, array $data): void
    {
        $purchaseExpense = PurchaseExpense::where('id', $data['id'])
            ->where('purchase_id', $purchase->id)
            ->firstOrFail();

        $expenseTx = $purchaseExpense->expenseTransaction;
        $category  = ExpenseCategory::findOrFail($data['expense_category_id']);
        $subject   = "{$category->name} — Purchase {$purchase->purchase_code}";

        $isPaid = $data['is_paid'] ?? false;

        $expenseTx->updateQuietly([
            'date'                => $purchase->date,
            'expense_month'       => $purchase->date->format('Y-m'),
            'expense_category_id' => $data['expense_category_id'],
            'account_id'          => $isPaid ? ($data['account_id'] ?? null) : null,
            'subject'             => $subject,
            'note'                => $subject,
            'amount'              => $data['amount'],
            'amount_usd'          => $data['amount_usd'],
            'currency_id'         => $data['currency_id'],
            'currency_rate'       => $data['currency_rate'],
        ]);

        $purchaseExpense->update([
            'exclude_from_item_cost' => $data['exclude_from_item_cost'] ?? false,
        ]);

        $existingPayment = $expenseTx->payments()->first();

        if ($isPaid) {
            if ($existingPayment) {
                $existingPayment->update([
                    'account_id'    => $data['account_id'],
                    'amount'        => $data['amount'],
                    'amount_usd'    => $data['amount_usd'],
                    'currency_id'   => $data['currency_id'],
                    'currency_rate' => $data['currency_rate'],
                    'note'          => $data['payment_note'] ?? null,
                ]);
                // Updated hook fires: reverses old balance, applies new
            } else {
                ExpensePayment::create([
                    'expense_transaction_id' => $expenseTx->id,
                    'account_id'             => $data['account_id'],
                    'amount'                 => $data['amount'],
                    'amount_usd'             => $data['amount_usd'],
                    'currency_id'            => $data['currency_id'],
                    'currency_rate'          => $data['currency_rate'],
                    'date'                   => $purchase->date,
                    'note'                   => $data['payment_note'] ?? null,
                ]);
            }
        } elseif ($existingPayment) {
            $existingPayment->delete();
            // Deleted hook fires: restores account balance
        }

        // Sync paid_amount on expense transaction
        ExpensePayment::syncExpensePaidAmount($expenseTx->id);
    }
}
