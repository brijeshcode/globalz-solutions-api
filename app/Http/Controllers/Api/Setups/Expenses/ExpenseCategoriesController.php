<?php

namespace App\Http\Controllers\Api\Setups\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Expenses\ExpenseCategoriesStoreRequest;
use App\Http\Requests\Api\Setups\Expenses\ExpenseCategoriesUpdateRequest;
use App\Http\Resources\Api\Setups\Expenses\ExpenseCategoryResource;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoriesController extends Controller
{
    use HasPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('parent_id')) {
            if ($request->input('parent_id') === 'null' || $request->input('parent_id') === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->input('parent_id'));
            }
        }

        if ($request->boolean('tree_structure')) {
            $query->whereNull('parent_id')->with('childrenRecursive');
        }

        $expenseCategories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Expense categories retrieved successfully',
            $expenseCategories,
            ExpenseCategoryResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseCategoriesStoreRequest $request): JsonResponse
    {
        $expenseCategory = ExpenseCategory::create($request->validated());
        $expenseCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name']);

        return ApiResponse::store(
            'Expense category created successfully',
            new ExpenseCategoryResource($expenseCategory)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name', 'children']);

        return ApiResponse::show(
            'Expense category retrieved successfully',
            new ExpenseCategoryResource($expenseCategory)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseCategoriesUpdateRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->update($request->validated());
        $expenseCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name']);

        return ApiResponse::update(
            'Expense category updated successfully',
            new ExpenseCategoryResource($expenseCategory)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->has_children) {
            return ApiResponse::custom('Cannot delete expense category that has children', 422);
        }

        $expenseCategory->delete();

        return ApiResponse::delete('Expense category deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = ExpenseCategory::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name'])
            ->searchable($request)
            ->sortable($request);

        $expenseCategories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed expense categories retrieved successfully',
            $expenseCategories,
            ExpenseCategoryResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $expenseCategory = ExpenseCategory::onlyTrashed()->findOrFail($id);
        $expenseCategory->restore();

        return ApiResponse::send('Expense category restored successfully', 200);
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $expenseCategory = ExpenseCategory::onlyTrashed()->findOrFail($id);
        $expenseCategory->forceDelete();

        return ApiResponse::send('Expense category permanently deleted', 200);
    }
}
