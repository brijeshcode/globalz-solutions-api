<?php

namespace App\Services\Suppliers;

use App\Helpers\FeatureHelper;
use App\Models\Expenses\ExpensePayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;

class PurchaseExpenseService
{
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

    public function computeExpenseShares(Purchase $purchase, array $items): array
    {
        $distributableUsd = $this->distributableUsd($purchase);
        $itemCount        = count($items);

        $subTotalUsd = collect($items)->sum(
            fn($i) => \App\Helpers\CurrencyHelper::toUsd($purchase->currency_id, $this->itemTotalPrice($i), $purchase->currency_rate)
        );

        return collect($items)->map(function ($itemData) use ($purchase, $distributableUsd, $subTotalUsd, $itemCount) {
            $itemTotalUsd = \App\Helpers\CurrencyHelper::toUsd($purchase->currency_id, $this->itemTotalPrice($itemData), $purchase->currency_rate);
            return $subTotalUsd > 0
                ? ($itemTotalUsd / $subTotalUsd) * $distributableUsd
                : ($itemCount > 0 ? $distributableUsd / $itemCount : 0);
        })->values()->toArray();
    }

    private function distributableUsd(Purchase $purchase): float
    {
        return (float) PurchaseExpense::where('purchase_id', $purchase->id)
            ->join('expense_transactions', 'purchase_expenses.expense_transaction_id', '=', 'expense_transactions.id')
            ->whereNull('expense_transactions.deleted_at')
            ->where('purchase_expenses.exclude_from_item_cost', false)
            ->sum('expense_transactions.amount_usd');
    }

    private function itemTotalPrice(array $itemData): float
    {
        $discountAmount  = $itemData['discount_amount'] ?? 0;
        $discountPercent = $itemData['discount_percent'] ?? 0;
        if ($discountPercent > 0) {
            $discountAmount = ($itemData['price'] * $itemData['quantity'] * $discountPercent) / 100;
        }
        return ($itemData['price'] * $itemData['quantity']) - $discountAmount;
    }

    public function recalculateItemCosts(Purchase $purchase): array
    {
        $purchase->refresh();

        $distributableUsd = $this->distributableUsd($purchase);

        $items       = $purchase->purchaseItems()->get();
        $subTotalUsd = (float) $purchase->sub_total_usd;
        $itemCount   = $items->count();
        $changedItems = [];

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

            $oldCostPerItemUsd = (float) $item->cost_per_item_usd;

            $item->updateQuietly([
                'total_expense_usd'    => $itemShare,
                'final_total_cost_usd' => $finalTotalCostUsd,
                'cost_per_item_usd'    => $costPerItemUsd,
            ]);

            if (abs($oldCostPerItemUsd - $costPerItemUsd) > 0.0001) {
                $changedItems[] = ['item' => $item->fresh(), 'old_cost' => $oldCostPerItemUsd];
            }
        }

        return $changedItems;
    }

    private function normalizeCurrency(array $data): array
    {
        $vatAmount = (float) ($data['vat_amount'] ?? 0);

        if (!FeatureHelper::isMultiCurrency()) {
            $data['currency_rate']  = 1;
            $data['amount_usd']     = $data['amount'];
            $data['vat_amount_usd'] = $vatAmount;
        } else {
            $data['vat_amount_usd'] = \App\Helpers\CurrencyHelper::toUsd(
                $data['currency_id'],
                $vatAmount,
                $data['currency_rate']
            );
        }

        $data['vat_amount'] = $vatAmount;

        return $data;
    }

    private function createExpenseLine(Purchase $purchase, array $data): void
    {
        $data     = $this->normalizeCurrency($data);
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $subject  = "{$category->name} — Purchase {$purchase->purchase_code}";

        $isPaid      = $data['is_paid'] ?? false;
        $date        = isset($data['date']) ? \Carbon\Carbon::parse($data['date']) : $purchase->date;
        $paymentDate = $data['payment_date'] ?? $date->toDateString();

        $totalAmount    = (float) $data['amount']     + (float) $data['vat_amount'];
        $totalAmountUsd = (float) $data['amount_usd'] + (float) $data['vat_amount_usd'];

        $expenseTx = ExpenseTransaction::create([
            'date'                => $date,
            'expense_month'       => $date->format('Y-m'),
            'expense_category_id' => $data['expense_category_id'],
            'account_id'          => $isPaid ? ($data['account_id'] ?? null) : null,
            'subject'             => $subject,
            'note'                => $data['payment_note'] ?? null,
            'amount'              => $data['amount'],
            'amount_usd'          => $data['amount_usd'],
            'currency_id'         => $data['currency_id'],
            'currency_rate'       => $data['currency_rate'],
            'paid_amount'         => 0,
            'paid_amount_usd'     => 0,
            'vat_amount'          => $data['vat_amount'],
            'vat_amount_usd'      => $data['vat_amount_usd'],
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
                'amount'                 => $totalAmount,
                'amount_usd'             => $totalAmountUsd,
                'currency_id'            => $data['currency_id'],
                'currency_rate'          => $data['currency_rate'],
                'date'                   => $paymentDate,
                'note'                   => $data['payment_note'] ?? null,
            ]);
            // Model boot hook fires: AccountsHelper::removeBalance + syncExpensePaidAmount
        }
    }

    private function updateExpenseLine(Purchase $purchase, array $data): void
    {
        $data            = $this->normalizeCurrency($data);
        $purchaseExpense = PurchaseExpense::where('id', $data['id'])
            ->where('purchase_id', $purchase->id)
            ->firstOrFail();

        $expenseTx = $purchaseExpense->expenseTransaction;
        $category  = ExpenseCategory::findOrFail($data['expense_category_id']);
        $subject   = "{$category->name} — Purchase {$purchase->purchase_code}";

        $isPaid      = $data['is_paid'] ?? false;
        $date        = isset($data['date']) ? \Carbon\Carbon::parse($data['date']) : $purchase->date;
        $paymentDate = \Carbon\Carbon::parse($data['payment_date'] ?? $date->toDateString())->setTime(12, 0, 0);
        $totalAmount    = (float) $data['amount']     + (float) $data['vat_amount'];
        $totalAmountUsd = (float) $data['amount_usd'] + (float) $data['vat_amount_usd'];

        $expenseTx->updateQuietly([
            'date'                => $date,
            'expense_month'       => $date->format('Y-m'),
            'expense_category_id' => $data['expense_category_id'],
            'account_id'          => $isPaid ? ($data['account_id'] ?? null) : null,
            'subject'             => $subject,
            'note'                => $data['payment_note'] ?? null,
            'amount'              => $data['amount'],
            'amount_usd'          => $data['amount_usd'],
            'currency_id'         => $data['currency_id'],
            'currency_rate'       => $data['currency_rate'],
            'vat_amount'          => $data['vat_amount'],
            'vat_amount_usd'      => $data['vat_amount_usd'],
        ]);

        $purchaseExpense->update([
            'exclude_from_item_cost' => $data['exclude_from_item_cost'] ?? false,
        ]);

        $existingPayment = $expenseTx->payments()->first();

        if ($isPaid) {
            if ($existingPayment) {
                $existingPayment->update([
                    'account_id'    => $data['account_id'],
                    'amount'        => $totalAmount,
                    'amount_usd'    => $totalAmountUsd,
                    'currency_id'   => $data['currency_id'],
                    'currency_rate' => $data['currency_rate'],
                    'date'          => $paymentDate,
                    'note'          => $data['payment_note'] ?? null,
                ]);
                // Updated hook fires: reverses old balance, applies new
            } else {
                ExpensePayment::create([
                    'expense_transaction_id' => $expenseTx->id,
                    'account_id'             => $data['account_id'],
                    'amount'                 => $totalAmount,
                    'amount_usd'             => $totalAmountUsd,
                    'currency_id'            => $data['currency_id'],
                    'currency_rate'          => $data['currency_rate'],
                    'date'                   => $paymentDate,
                    'note'                   => $data['payment_note'] ?? null,
                ]);
            }
        } elseif ($existingPayment) {
            $existingPayment->delete();
            // Deleted hook fires: restores account balance
        }

        ExpensePayment::syncExpensePaidAmount($expenseTx->id);
    }
}
