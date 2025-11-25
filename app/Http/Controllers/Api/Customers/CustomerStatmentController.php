<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\DataHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerStatmentController extends Controller
{
    use HasPagination;
    
    public function customerStatements(Request $request, Customer $customer ): JsonResponse
    {
        // Check if salesman can access this customer
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if (!$employee) {
                return ApiResponse::customError('You are not authorized to view this customer\'s statement', 403);
            }
            if ($customer->salesperson_id !== $employee->id) {
                return ApiResponse::customError('You are not authorized to view this customer\'s statement', 403);
            }
        }
        $search = $request->get('search');

        $allTransactions = $this->getTransactions($request, $customer, $search);
        $stats = $this->calculateStats($allTransactions);
        $this->canUpdateBalance($request, $customer, $stats['balance']);

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
                'Customer statement retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index(
            'Customer statement retrieved successfully',
            $allTransactions,
            $stats
        );
    }

    public function statements(Request $request): JsonResponse
    {
        $customerSearch = $request->get('customer'); // customer code or name
        $noteSearch = $request->get('note');

        // Find customer by code or name if provided
        $customer = null;

        $query = Customer::query()->where('code', $customerSearch)
            ->orWhere('name', 'like', "%{$customerSearch}%")
            ;

        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
            }
        }

        $customer = $query->first();


        if (!$customer) {
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

        $allTransactions = $this->getTransactions($request, $customer, $noteSearch);

        $stats = $this->calculateStats($allTransactions);

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

    private function canUpdateBalance(Request $request, Customer $customer, float $balance): void
    {
        // Only update balance if no filters are applied
        $hasFilters = $request->has('from_date')
            || $request->has('to_date')
            || $request->has('search')
            || $request->has('transaction_type');

        if ($hasFilters) {
            return;
        }

        if($customer->current_balance == $balance){
            return;
        }

        // Update customer balance
        $customer->update(['current_balance' => $balance]);
    }

    private function getTransactions(Request $request, Customer $customer, ?string $noteSearch = null)
    {
        $transactionType = $request->get('transaction_type');

        $allTransactions = collect();

        if (!$transactionType || $transactionType === 'credit_debit_note') {
            $allTransactions = $allTransactions->concat(
                $this->getCreditDebitNotes($request, $customer, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'sale') {
            $allTransactions = $allTransactions->concat(
                $this->getSales($request, $customer, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'payment') {
            $allTransactions = $allTransactions->concat(
                $this->getPayments($request, $customer, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'return') {
            $allTransactions = $allTransactions->concat(
                $this->getReturns($request, $customer, $noteSearch)
            );
        }

        // Sort by timestamp
        $sortedTransactions = $allTransactions->sortBy('timestamp')->values();

        // Calculate running balance
        $balance = 0;
        $transactionsWithBalance = $sortedTransactions->map(function ($transaction) use (&$balance) {
            $balance += $transaction['credit'] - $transaction['debit'];
            $transaction['balance'] = $balance;
            return $transaction;
        });

        return $transactionsWithBalance->values();
    }

    private function getCreditDebitNotes(Request $request, Customer $customer, ?string $noteSearch = null)
    {
        $query = CustomerCreditDebitNote::query()
            ->select('id', 'code', 'prefix', 'date', 'type', 'amount_usd', 'note', 'customer_id', 'created_at');

        $this->applyFilters($query, $request, $customer, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => $item->type == 'credit' ? 'Credit Note' : 'Debit Note',
                'date' => $item->date,
                'amount' => $item->type === 'credit' ? -$item->amount_usd : $item->amount_usd,
                'debit' => $item->type === 'debit' ? $item->amount_usd : 0,
                'credit' => $item->type === 'credit' ? $item->amount_usd : 0,
                'note' => $item->note,
                'customer' => [
                    'id' => $item->customer->id,
                    'code' => $item->customer->code,
                    'name' => $item->customer->name,
                ],
                'transaction_type' => 'credit_debit_note',
                'source_table' => 'customer_credit_debit_notes',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getSales(Request $request, Customer $customer, ?string $noteSearch = null)
    {
        $query = Sale::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'customer_id', 'created_at');

        $this->applyFilters($query, $request, $customer, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Sale Invoice',
                'date' => $item->date,
                'amount' => $item->total_usd,
                'debit' => $item->total_usd,
                'credit' => 0,
                'note' => $item->note,
                'customer' => [
                    'id' => $item->customer->id,
                    'code' => $item->customer->code,
                    'name' => $item->customer->name,
                ],
                'transaction_type' => 'sale',
                'source_table' => 'sales',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getPayments(Request $request, Customer $customer, ?string $noteSearch = null)
    {
        $query = CustomerPayment::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'amount_usd', 'note', 'customer_id', 'created_at');

        $this->applyFilters($query, $request, $customer, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Payment',
                'date' => $item->date,
                'amount' => -$item->amount_usd,
                'debit' => 0,
                'credit' => $item->amount_usd,
                'note' => $item->note,
                'customer' => [
                    'id' => $item->customer->id,
                    'code' => $item->customer->code,
                    'name' => $item->customer->name,
                ],
                'transaction_type' => 'payment',
                'source_table' => 'customer_payments',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getReturns(Request $request, Customer $customer, ?string $noteSearch = null)
    {
        $query = CustomerReturn::query()
            ->approved()
            ->received()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'customer_id', 'created_at');

        $this->applyFilters($query, $request, $customer, $noteSearch);

        return $query->with('customer:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Sales Return',
                'date' => $item->date,
                'amount' => -$item->total_usd,
                'debit' => 0,
                'credit' => $item->total_usd,
                'note' => $item->note,
                'customer' => [
                    'id' => $item->customer->id,
                    'code' => $item->customer->code,
                    'name' => $item->customer->name,
                ],
                'transaction_type' => 'return',
                'source_table' => 'customer_returns',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function calculateStats($transactions)
    {
        return [
            'total_debit' => $transactions->sum('debit'),
            'total_credit' => $transactions->sum('credit'),
            'balance' => $transactions->last()['balance'] ?? 0,
        ];
    }

    private function applyFilters($query, Request $request, Customer $customer, ?string $noteSearch = null)
    {
        if ($customer) {
            $query->where('customer_id', $customer->id);
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
                  ->orWhere('code', 'like', "%{$noteSearch}%")
                  ->orWhere('prefix', 'like', "%{$noteSearch}%");
            });
        }
    }
}
