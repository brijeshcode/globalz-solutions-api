<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\DataHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Customers\CustomerPayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Suppliers\Purchase;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountStatementController extends Controller
{
    use HasPagination;

    public function accountStatements(Request $request, Account $account): JsonResponse
    {
        $search = $request->get('search');

        $allTransactions = $this->getTransactions($request, $account, $search);

        $stats = $this->calculateStats($allTransactions, $account);

        $this->canUpdateBalance($account, $stats['balance']);
        // Check if pagination is requested
        if ($request->boolean('withPage')) {
            // Paginate using DataHelper
            $paginatedTransactions = DataHelper::customPaginate(
                $allTransactions,
                $this->getPerPage($request),
                $request->get('page', 1),
                $request
            );

            return ApiResponse::paginated(
                'Account statement retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index(
            'Account statement retrieved successfully',
            $allTransactions,
            $stats
        );
    }

    private function canUpdateBalance(Account $account, float $balance): void
    {
        if($account->current_balance == $balance){
            return;
        }
        $account->update(['current_balance' => $balance]);
    }

    public function statements(Request $request): JsonResponse
    {
        $accountSearch = $request->get('account'); // account name
        $noteSearch = $request->get('note');

        // Find account by name if provided
        $account = null;

        if ($accountSearch) {
            $account = Account::query()
                ->where('name', 'like', "%{$accountSearch}%")
                ->first();
        }

        if (!$account) {
            if ($request->boolean('withPage')) {
                return ApiResponse::paginated(
                    'Statement retrieved successfully',
                    DataHelper::emptyPaginate($this->getPerPage($request), $request)
                );
            }

            return ApiResponse::index(
                'Statement retrieved successfully',
                []
            );
        }

        $allTransactions = $this->getTransactions($request, $account, $noteSearch);

        $stats = $this->calculateStats($allTransactions, $account);

        // Check if pagination is requested
        if ($request->boolean('withPage')) {
            // Paginate using DataHelper
            $paginatedTransactions = DataHelper::customPaginate(
                $allTransactions,
                $this->getPerPage($request),
                $request->get('page', 1),
                $request
            );

            return ApiResponse::paginated(
                'Statements retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index(
            'Statements retrieved successfully',
            $allTransactions,
            $stats
        );
    }

    private function getTransactions(Request $request, Account $account, ?string $noteSearch = null)
    {
        $transactionType = $request->get('transaction_type');

        $allTransactions = collect();

        // Add opening balance as first transaction
        $openingBalance = $account->opening_balance ?? 0;
        if ($openingBalance != 0) {
            $allTransactions->push([
                'id' => null,
                'code' => 'OPENING',
                'type' => 'Opening Balance',
                'date' => $account->created_at->format('Y-m-d'),
                'amount' => $openingBalance,
                'debit' => $openingBalance < 0 ? $openingBalance : 0,
                'credit' => $openingBalance > 0 ? abs($openingBalance) : 0,
                'note' => 'Opening balance',
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'opening_balance',
                'source_table' => 'accounts',
                'timestamp' => $account->created_at->timestamp,
            ]);
        }

        if (!$transactionType || $transactionType === 'customer_payment') {
            $allTransactions = $allTransactions->concat(
                $this->getCustomerPayments($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'purchase') {
            $allTransactions = $allTransactions->concat(
                $this->getPurchases($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'expense') {
            $allTransactions = $allTransactions->concat(
                $this->getExpenseTransactions($request, $account, $noteSearch)
            );
        }

        // Sort by timestamp
        $sortedTransactions = $allTransactions->sortBy('date')->values();

        // Calculate running balance
        $balance = 0;
        $transactionsWithBalance = $sortedTransactions->map(function ($transaction) use (&$balance) {
            $balance += $transaction['credit'] - $transaction['debit'] ;
            $transaction['balance'] = $balance;
            return $transaction;
        });

        return $transactionsWithBalance->values();
    }

    private function getCustomerPayments(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = CustomerPayment::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'amount_usd', 'note', 'customer_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Customer Payment',
                'date' => $item->date->format('Y-m-d'),
                'amount' => $item->amount_usd,
                'debit' => 0,
                'credit' => $item->amount_usd,
                'note' => $item->note,
                'customer' => [
                    'id' => $item->customer->id,
                    'code' => $item->customer->code,
                    'name' => $item->customer->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'customer_payment',
                'source_table' => 'customer_payments',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getPurchases(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = Purchase::query()
            ->select('id', 'code', 'date', 'final_total_usd', 'note', 'supplier_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'type' => 'Purchase',
                'date' => $item->date->format('Y-m-d'),
                'amount' => -$item->final_total_usd,
                'debit' => $item->final_total_usd,
                'credit' => 0,
                'note' => $item->note,
                'supplier' => [
                    'id' => $item->supplier->id,
                    'code' => $item->supplier->code,
                    'name' => $item->supplier->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'purchase',
                'source_table' => 'purchases',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getExpenseTransactions(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = ExpenseTransaction::query()
            ->select('id', 'code', 'date', 'amount', 'subject', 'note', 'expense_category_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('expenseCategory:id,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'type' => 'Expense',
                'date' => $item->date->format('Y-m-d'),
                'amount' => -$item->amount,
                'debit' => $item->amount,
                'credit' => 0,
                'note' => $item->note ?? $item->subject,
                'expense_category' => [
                    'id' => $item->expenseCategory->id,
                    'name' => $item->expenseCategory->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'expense',
                'source_table' => 'expense_transactions',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function calculateStats($transactions, Account $account)
    {
        $openingBalance = $account->opening_balance ?? 0;

        return [
            'opening_balance' => $openingBalance,
            'total_debit' => $transactions->sum('debit'),
            'total_credit' => $transactions->sum('credit'),
            'balance' => $transactions->last()['balance'] ?? $openingBalance,
            'current_balance' => $account->current_balance ?? 0,
        ];
    }

    private function applyFilters($query, Request $request, Account $account, ?string $noteSearch = null)
    {
        if ($account) {
            $query->where('account_id', $account->id);
        }

        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->get('to_date'));
        }

        if ($noteSearch) {
            $query->where(function ($q) use ($noteSearch) {
                $q->where('note', 'like', "%{$noteSearch}%")
                  ->orWhere('code', 'like', "%{$noteSearch}%");
            });
        }
    }
}
