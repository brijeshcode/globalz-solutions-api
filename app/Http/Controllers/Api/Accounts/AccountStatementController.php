<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\CurrencyHelper;
use App\Helpers\DataHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Accounts\AccountTransfer;
use App\Models\Accounts\IncomeTransaction;
use App\Models\Accounts\AccountAdjust;
use App\Models\Customers\CustomerPayment;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\Salary;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Suppliers\SupplierPayment;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Current;

class AccountStatementController extends Controller
{
    use HasPagination;

    public function accountStatements(Request $request, Account $account): JsonResponse
    {
        $search = $request->get('search');

        $result = $this->getTransactions($request, $account, $search);
        $allTransactions = $result['transactions'];
        $finalBalance = $result['final_balance'];

        $stats = $this->calculateStats($allTransactions, $account, $finalBalance);

        $this->canUpdateBalance($request, $account, $stats['balance']);
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

    private function canUpdateBalance(Request $request, Account $account, float $balance): void
    {
        // Only update balance if no filters are applied
        $hasFilters = $request->has('from_date')
            || $request->has('to_date')
            || $request->has('search')
            || $request->has('transaction_type');

        if ($hasFilters) {
            return;
        }

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

        $result = $this->getTransactions($request, $account, $noteSearch);
        $allTransactions = $result['transactions'];
        $finalBalance = $result['final_balance'];

        $stats = $this->calculateStats($allTransactions, $account, $finalBalance);

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
                'transaction_number' => 'OPENING',
                'prefix' => '',
                'name' => $account->name,
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

        if (!$transactionType || $transactionType === 'supplier_payment') {
            $allTransactions = $allTransactions->concat(
                $this->getSupplierPayments($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'expense') {
            $allTransactions = $allTransactions->concat(
                $this->getExpenseTransactions($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'advance_loan') {
            $allTransactions = $allTransactions->concat(
                $this->getAdvanceLoanTransactions($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'salary') {
            $allTransactions = $allTransactions->concat(
                $this->getSalaryTransactions($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'income') {
            $allTransactions = $allTransactions->concat(
                $this->getIncomeTransactions($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'account_transfer') {
            $allTransactions = $allTransactions->concat(
                $this->getAccountTransfers($request, $account, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'account_adjust') {
            $allTransactions = $allTransactions->concat(
                $this->getAccountAdjusts($request, $account, $noteSearch)
            );
        }

        // Sort by date ascending to calculate running balance correctly
        $sortedTransactions = $allTransactions->sortBy('timestamp')->values();

        // Calculate running balance
        $balance = 0;
        $transactionsWithBalance = $sortedTransactions->map(function ($transaction) use (&$balance) {
            $balance += $transaction['credit'] - $transaction['debit'] ;
            $transaction['balance'] = $balance;
            return $transaction;
        });

        // Store final balance before reversing
        $finalBalance = $balance;

        // Reverse to show latest transactions on top
        return [
            'transactions' => $transactionsWithBalance->reverse()->values(),
            'final_balance' => $finalBalance,
        ];
    }

    private function getCustomerPayments(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = CustomerPayment::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'amount', 'amount_usd', 'note', 'customer_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'type' => 'Customer Payment',
                'name' => $item->customer->name,
                'date' => $item->date->format('Y-m-d'),
                'amount' => $item->amount,
                'debit' => 0,
                'credit' => $item->amount,
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

    private function getSupplierPayments(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = SupplierPayment::query()
            ->select('id', 'code', 'prefix', 'date', 'amount', 'amount_usd', 'note', 'supplier_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'type' => 'Supplier Payment',
                'name' => $item->supplier->name,
                'date' => $item->date->format('Y-m-d'),
                'amount' => -$item->amount,
                'debit' => $item->amount,
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
                'transaction_type' => 'supplier_payment',
                'source_table' => 'supplier_payments',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getIncomeTransactions(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = IncomeTransaction::query()
            ->select('id', 'code', 'date', 'amount', 'subject', 'note', 'income_category_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('incomeCategory:id,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->code,
                'code' => $item->getRawOriginal('code'),
                'prefix' => IncomeTransaction::PREFIX,
                
                'name' => $item->incomeCategory->name,
                'type' => 'Income',
                'date' => $item->date->format('Y-m-d'),
                'amount' => $item->amount,
                'debit' => 0,
                'credit' => $item->amount,
                'note' => $item->note ?? $item->subject,
                'income_category' => [
                    'id' => $item->incomeCategory->id,
                    'name' => $item->incomeCategory->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'income',
                'source_table' => 'income_transactions',
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
                'transaction_number' => $item->code,
                'code' => $item->getRawOriginal('code'),
                'prefix' => ExpenseTransaction::PREFIX,
                'name' => $item->expenseCategory->name,
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

    private function getAdvanceLoanTransactions(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = AdvanceLoan::query()
            ->select('id', 'code', 'prefix', 'date', 'amount', 'amount_usd', 'note', 'employee_id', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('employee:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'type' => 'AdvanceLoan',
                'name' => $item->employee->name,
                'date' => $item->date->format('Y-m-d'),
                'amount' => $item->amount,
                'debit' => $item->amount,
                'credit' => 0,
                'note' => $item->note,
                'employee' => [
                    'id' => $item->employee->id,
                    'code' => $item->employee->code,
                    'name' => $item->employee->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'advance_loan',
                'source_table' => 'advance_loans',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getSalaryTransactions(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = Salary::query()
            ->select('id', 'code', 'prefix', 'date', 'final_total', 'amount_usd', 'note', 'employee_id', 'account_id', 'month', 'year', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->with('employee:id,code,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'type' => 'Salary',
                'name' => $item->employee->name,
                'date' => $item->date->format('Y-m-d'),
                'amount' => -$item->final_total,
                'debit' => $item->final_total,
                'credit' => 0,
                'note' => $item->note ?? "Salary for {$item->month}/{$item->year}",
                'employee' => [
                    'id' => $item->employee->id,
                    'code' => $item->employee->code,
                    'name' => $item->employee->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'salary',
                'source_table' => 'salaries',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getAccountTransfers(Request $request, Account $account, ?string $noteSearch = null)
    {
        $transfers = collect();

        // Get transfers where this account is sending money (debit)
        $queryFrom = AccountTransfer::query()
            ->select('id', 'code', 'prefix', 'date', 'sent_amount', 'note', 'from_account_id', 'to_account_id', 'created_at')
            ->where('from_account_id', $account->id);

        $this->applyDateAndSearchFilters($queryFrom, $request, $noteSearch);

        $transfersFrom = $queryFrom->with('toAccount:id,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'name' => $account->name,
                'type' => 'Account Transfer (Sent)',
                'date' => $item->date->format('Y-m-d'),
                'amount' => -$item->sent_amount,
                'debit' => $item->sent_amount,
                'credit' => 0,
                'note' => $item->note,
                'to_account' => [
                    'id' => $item->toAccount->id,
                    'name' => $item->toAccount->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'account_transfer_sent',
                'source_table' => 'account_transfers',
                'timestamp' => $item->date->timestamp,
            ];
        });

        // Get transfers where this account is receiving money (credit)
        $queryTo = AccountTransfer::query()
            ->select('id', 'code', 'prefix', 'date', 'received_amount', 'note', 'from_account_id', 'to_account_id', 'created_at')
            ->where('to_account_id', $account->id);

        $this->applyDateAndSearchFilters($queryTo, $request, $noteSearch);

        $transfersTo = $queryTo->with('fromAccount:id,name')->get()->map(function ($item) use ($account) {
            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'name' => $account->name,
                'type' => 'Account Transfer (Received)',
                'date' => $item->date->format('Y-m-d'),
                'amount' => $item->received_amount,
                'debit' => 0,
                'credit' => $item->received_amount,
                'note' => $item->note,
                'from_account' => [
                    'id' => $item->fromAccount->id,
                    'name' => $item->fromAccount->name,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'account_transfer_received',
                'source_table' => 'account_transfers',
                'timestamp' => $item->date->timestamp,
            ];
        });

        return $transfers->concat($transfersFrom)->concat($transfersTo);
    }

    private function getAccountAdjusts(Request $request, Account $account, ?string $noteSearch = null)
    {
        $query = AccountAdjust::query()
            ->select('id', 'code', 'prefix', 'date', 'type', 'amount', 'note', 'account_id', 'created_at');

        $this->applyFilters($query, $request, $account, $noteSearch);

        return $query->get()->map(function ($item) use ($account) {
            $isCredit = $item->type === 'Credit';

            return [
                'id' => $item->id,
                'transaction_number' => $item->prefix . $item->code,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'name' => $account->name,
                'type' => 'Account Adjust (' . $item->type . ')',
                'date' => $item->date->format('Y-m-d'),
                'amount' => $isCredit ? $item->amount : -$item->amount,
                'debit' => $isCredit ? 0 : $item->amount,
                'credit' => $isCredit ? $item->amount : 0,
                'note' => $item->note,
                'adjust_type' => $item->type,
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'transaction_type' => 'account_adjust',
                'source_table' => 'account_adjusts',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function calculateStats($transactions, Account $account, float $finalBalance)
    {
        $openingBalance = $account->opening_balance ?? 0;

        return [
            'opening_balance' => $openingBalance,
            'total_debit' => $transactions->sum('debit'),
            'total_credit' => $transactions->sum('credit'),
            'balance' => $finalBalance,
            'current_balance' => $account->current_balance ?? 0,
            'balance_in_usd' => CurrencyHelper::toUsd($account->currency_id, $finalBalance)
        ];
    }

    private function applyDateAndSearchFilters($query, Request $request, ?string $noteSearch = null)
    {
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

    private function applyFilters($query, Request $request, Account $account, ?string $noteSearch = null)
    {
        $query->where('account_id', $account->id);
        $this->applyDateAndSearchFilters($query, $request, $noteSearch);
    }
}
