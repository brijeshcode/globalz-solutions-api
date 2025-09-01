<?php

namespace App\Http\Controllers\Api\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\ExpenseTransactionsStoreRequest;
use App\Http\Requests\Api\Expenses\ExpenseTransactionsUpdateRequest;
use App\Http\Resources\Api\Expenses\ExpenseTransactionResource;
use App\Models\Expenses\ExpenseTransaction;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseTransactionsController extends Controller
{
    use HasPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseTransaction::query()
            ->with([
                'createdBy:id,name', 
                'updatedBy:id,name', 
                'expenseCategory:id,name', 
                'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->input('expense_category_id'));
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->input('start_date'), $request->input('end_date'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        $expenseTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Expense transactions retrieved successfully',
            $expenseTransactions,
            ExpenseTransactionResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseTransactionsStoreRequest $request): JsonResponse
    {
        $expenseTransaction = ExpenseTransaction::create($request->validated());
        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name'
        ]);

        return ApiResponse::store(
            'Expense transaction created successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name'
        ]);

        return ApiResponse::show(
            'Expense transaction retrieved successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseTransactionsUpdateRequest $request, ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->update($request->validated());
        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name'
        ]);

        return ApiResponse::update(
            'Expense transaction updated successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->delete();

        return ApiResponse::delete('Expense transaction deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = ExpenseTransaction::onlyTrashed()
            ->with([
                'createdBy:id,name', 
                'updatedBy:id,name', 
                'expenseCategory:id,name', 
                'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        $expenseTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed expense transactions retrieved successfully',
            $expenseTransactions,
            ExpenseTransactionResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $expenseTransaction = ExpenseTransaction::onlyTrashed()->findOrFail($id);
        $expenseTransaction->restore();

        return ApiResponse::update('Expense transaction restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $expenseTransaction = ExpenseTransaction::onlyTrashed()->findOrFail($id);
        $expenseTransaction->forceDelete();

        return ApiResponse::delete('Expense transaction permanently deleted successfully');
    }
}
