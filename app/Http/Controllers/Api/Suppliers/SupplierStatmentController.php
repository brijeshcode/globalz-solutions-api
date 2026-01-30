<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\DataHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Supplier;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Models\Suppliers\SupplierPayment;
use App\Models\Suppliers\PurchaseReturn;
use App\Models\Suppliers\Purchase;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierStatmentController extends Controller
{
    use HasPagination;

    public function supplierStatements(Request $request, Supplier $supplier): JsonResponse
    {
        $search = $request->get('search');

        $allTransactions = $this->getTransactions($request, $supplier, $search);
        $stats = $this->calculateStats($allTransactions);
        $this->canUpdateBalance($request, $supplier, $stats['balance']);

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
                'Supplier statement retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index(
            'Supplier statement retrieved successfully',
            $allTransactions,
            $stats
        );
    }

    /**
     * Recalculate balance for all suppliers
     */
    public function recalculateAllBalances(Request $request): JsonResponse
    {
        // Only allow admin to recalculate all balances
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can recalculate all supplier balances', 403);
        }

        // Increase time limit for large datasets
        set_time_limit(3600); // 1 hour

        $result = $this->processBalanceRecalculation();

        return ApiResponse::show(
            "Balance recalculation completed. {$result['updated_count']} supplier(s) updated out of {$result['total_suppliers']} total suppliers.",
            $result
        );
    }

    /**
     * Process balance recalculation for all suppliers
     * This method can be called from other controllers
     * Uses chunking to prevent memory issues with large datasets
     */
    public function processBalanceRecalculation(?array $supplierIds = null): array
    {
        $query = Supplier::query()->active();

        // If specific supplier IDs are provided, only recalculate those
        if ($supplierIds !== null) {
            $query->whereIn('id', $supplierIds);
        }

        $totalSuppliers = $query->count();
        $updatedCount = 0;
        $results = [];

        // Process suppliers in chunks of 100 to prevent memory issues
        $query->chunk(100, function ($suppliers) use (&$updatedCount, &$results) {
            foreach ($suppliers as $supplier) {
                $supplierResult = $this->processSupplierBalanceRecalculation($supplier);

                if ($supplierResult['updated']) {
                    $updatedCount++;
                    $results[] = $supplierResult;
                }
            }
        });

        return [
            'total_suppliers' => $totalSuppliers,
            'updated_count' => $updatedCount,
            'unchanged_count' => $totalSuppliers - $updatedCount,
            'updated_suppliers' => $results,
        ];
    }

    /**
     * Process balance recalculation for a single supplier
     * This method can be called from other controllers
     */
    public function processSupplierBalanceRecalculation(Supplier $supplier): array
    {
        // Create empty request for getTransactions
        $request = new Request();

        $transactions = $this->getTransactions($request, $supplier, null);

        // Calculate the correct balance
        $calculatedBalance = $transactions->last()['balance'] ?? 0;
        $oldBalance = $supplier->current_balance;

        // Check if balance is different
        if ($supplier->current_balance != $calculatedBalance) {
            // Update the supplier balance
            $supplier->update(['current_balance' => $calculatedBalance]);

            return [
                'updated' => true,
                'supplier_id' => $supplier->id,
                'supplier_code' => $supplier->code,
                'supplier_name' => $supplier->name,
                'old_balance' => $oldBalance,
                'new_balance' => $calculatedBalance,
                'difference' => $calculatedBalance - $oldBalance,
            ];
        }

        return [
            'updated' => false,
            'supplier_id' => $supplier->id,
            'supplier_code' => $supplier->code,
            'supplier_name' => $supplier->name,
            'current_balance' => $oldBalance,
        ];
    }

    public function statements(Request $request): JsonResponse
    {
        $supplierSearch = $request->get('supplier'); // supplier code or name
        $noteSearch = $request->get('note');

        // Find supplier by code or name if provided
        $supplier = null;

        $query = Supplier::query()->where('code', $supplierSearch)
            ->orWhere('name', 'like', "%{$supplierSearch}%");

        $supplier = $query->first();

        if (!$supplier) {
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

        $allTransactions = $this->getTransactions($request, $supplier, $noteSearch);

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

    private function canUpdateBalance(Request $request, Supplier $supplier, float $balance): void
    {
        // Only update balance if no filters are applied
        $hasFilters = $request->has('from_date')
            || $request->has('to_date')
            || $request->has('search')
            || $request->has('transaction_type');

        if ($hasFilters) {
            return;
        }

        if($supplier->current_balance == $balance){
            return;
        }

        // Update supplier balance
        $supplier->update(['current_balance' => $balance]);
    }

    private function getTransactions(Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        $transactionType = $request->get('transaction_type');

        $allTransactions = collect();

        if (!$transactionType || $transactionType === 'credit_debit_note') {
            $allTransactions = $allTransactions->concat(
                $this->getCreditDebitNotes($request, $supplier, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'purchase') {
            $allTransactions = $allTransactions->concat(
                $this->getPurchases($request, $supplier, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'payment') {
            $allTransactions = $allTransactions->concat(
                $this->getPayments($request, $supplier, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'return') {
            $allTransactions = $allTransactions->concat(
                $this->getReturns($request, $supplier, $noteSearch)
            );
        }

        // Sort by date - default to desc
        $sortDirection = $request->get('sort_direction', 'desc');
        $sortedTransactions = $sortDirection === 'asc'
            ? $allTransactions->sortBy('date')->values()
            : $allTransactions->sortByDesc('date')->values(); 

        // Calculate running balance
        $balance = 0;
        $transactionsWithBalance = $sortedTransactions->map(function ($transaction) use (&$balance) {
            $balance += $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $balance;
            return $transaction;
        });

        return $transactionsWithBalance->values();
    }

    private function getCreditDebitNotes(Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        $query = SupplierCreditDebitNote::query()
            ->select('id', 'code', 'prefix', 'date', 'type', 'amount_usd', 'note', 'supplier_id', 'created_at');

        $this->applyFilters($query, $request, $supplier, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => $item->type == 'credit' ? 'Credit Note' : 'Debit Note',
                'date' => $item->date,
                'amount' => $item->type === 'credit' ? -$item->amount_usd : $item->amount_usd,
                'debit' => $item->type === 'debit' ? $item->amount_usd : 0,
                'credit' => $item->type === 'credit' ? $item->amount_usd : 0,
                'note' => $item->note,
                'supplier' => [
                    'id' => $item->supplier->id,
                    'code' => $item->supplier->code,
                    'name' => $item->supplier->name,
                ],
                'transaction_type' => 'credit_debit_note',
                'source_table' => 'supplier_credit_debit_notes',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getPurchases(Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        $query = Purchase::query()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'supplier_id', 'created_at');

        $this->applyFilters($query, $request, $supplier, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Purchase',
                'date' => $item->date,
                'amount' => $item->total_usd,
                'debit' => $item->total_usd,
                'credit' => 0,
                'note' => $item->note,
                'supplier' => [
                    'id' => $item->supplier->id,
                    'code' => $item->supplier->code,
                    'name' => $item->supplier->name,
                ],
                'transaction_type' => 'purchase',
                'source_table' => 'purchases',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getPayments(Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        $query = SupplierPayment::query()
            ->select('id', 'code', 'prefix', 'date', 'amount_usd', 'note', 'supplier_id', 'created_at');

        $this->applyFilters($query, $request, $supplier, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Payment',
                'date' => $item->date,
                'amount' => -$item->amount_usd,
                'debit' => 0,
                'credit' => $item->amount_usd,
                'note' => $item->note,
                'supplier' => [
                    'id' => $item->supplier->id,
                    'code' => $item->supplier->code,
                    'name' => $item->supplier->name,
                ],
                'transaction_type' => 'payment',
                'source_table' => 'supplier_payments',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getReturns(Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        $query = PurchaseReturn::query()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'supplier_id', 'created_at');

        $this->applyFilters($query, $request, $supplier, $noteSearch);

        return $query->with('supplier:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'type' => 'Purchase Return',
                'date' => $item->date,
                'amount' => -$item->total_usd,
                'debit' => 0,
                'credit' => $item->total_usd,
                'note' => $item->note,
                'supplier' => [
                    'id' => $item->supplier->id,
                    'code' => $item->supplier->code,
                    'name' => $item->supplier->name,
                ],
                'transaction_type' => 'purchase_returns',
                'source_table' => 'purchase_returns',
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

    private function applyFilters($query, Request $request, Supplier $supplier, ?string $noteSearch = null)
    {
        if ($supplier) {
            $query->where('supplier_id', $supplier->id);
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
