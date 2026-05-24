<?php

namespace App\Http\Controllers\Api\Setups\Expenses;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseExpenseSubcategoryController extends Controller
{
    private function getParent(): ExpenseCategory
    {
        return ExpenseCategory::where('name', 'Purchase Expenses')
            ->where('is_system', true)
            ->firstOrFail();
    }

    public function index(): JsonResponse
    {
        $parent = $this->getParent();

        $categories = ExpenseCategory::where('parent_id', $parent->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::show('Purchase expense subcategories retrieved', $categories);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(RoleHelper::canAdmin(), 403);

        $parent = $this->getParent();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('expense_categories', 'name')
                    ->where('parent_id', $parent->id)
                    ->whereNull('deleted_at'),
            ],
            'description'         => 'nullable|string|max:500',
            'is_active'           => 'boolean',
            'exclude_from_profit' => 'boolean',
            'is_vat_category'     => 'boolean',
        ]);

        $category = ExpenseCategory::create(array_merge($data, [
            'parent_id' => $parent->id,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return ApiResponse::store('Purchase expense subcategory created', $category);
    }

    public function update(Request $request, ExpenseCategory $purchaseExpenseSubcategory): JsonResponse
    {
        abort_unless(RoleHelper::canAdmin(), 403);

        if ($purchaseExpenseSubcategory->is_system) {
            return ApiResponse::customError('System categories cannot be modified.', 422);
        }

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('expense_categories', 'name')
                    ->where('parent_id', $purchaseExpenseSubcategory->parent_id)
                    ->ignore($purchaseExpenseSubcategory)
                    ->whereNull('deleted_at'),
            ],
            'description'         => 'nullable|string|max:500',
            'is_active'           => 'boolean',
            'exclude_from_profit' => 'boolean',
            'is_vat_category'     => 'boolean',
        ]);

        $purchaseExpenseSubcategory->update($data);

        return ApiResponse::show('Purchase expense subcategory updated', $purchaseExpenseSubcategory->fresh());
    }

    public function destroy(ExpenseCategory $purchaseExpenseSubcategory): JsonResponse
    {
        abort_unless(RoleHelper::canAdmin(), 403);

        if ($purchaseExpenseSubcategory->is_system) {
            return ApiResponse::customError('System categories cannot be deleted.', 422);
        }

        $hasExpenses = ExpenseTransaction::where('expense_category_id', $purchaseExpenseSubcategory->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasExpenses) {
            return ApiResponse::customError(
                'Cannot delete this subcategory — it has linked expense transactions.',
                422
            );
        }

        $purchaseExpenseSubcategory->delete();

        return ApiResponse::show('Purchase expense subcategory deleted', null);
    }
}
