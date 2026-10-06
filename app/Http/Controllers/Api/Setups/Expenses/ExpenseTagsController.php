<?php

namespace App\Http\Controllers\Api\Setups\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Expenses\ExpenseTagStoreRequest;
use App\Http\Requests\Api\Setups\Expenses\ExpenseTagSyncCategoriesRequest;
use App\Http\Requests\Api\Setups\Expenses\ExpenseTagUpdateRequest;
use App\Http\Resources\Api\Setups\Expenses\ExpenseTagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Expenses\ExpenseTag;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseTagsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        ExpenseTag::ensureSystemTags();

        $query = ExpenseTag::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ApiResponse::paginated(
            'Expense tags retrieved successfully',
            $this->applyPagination($query, $request),
            ExpenseTagResource::class
        );
    }

    public function show(ExpenseTag $expenseTag): JsonResponse
    {
        $expenseTag->load(['categories:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Expense tag retrieved successfully', new ExpenseTagResource($expenseTag));
    }

    public function store(ExpenseTagStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = $this->uniqueCode($data['name']);
        $data['is_system'] = false;

        $expenseTag = ExpenseTag::create($data);
        $expenseTag->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Expense tag created successfully', new ExpenseTagResource($expenseTag));
    }

    public function update(ExpenseTagUpdateRequest $request, ExpenseTag $expenseTag): JsonResponse
    {
        if ($expenseTag->is_system && $request->has('name') && $request->input('name') !== $expenseTag->name) {
            return ApiResponse::custom('System tags cannot be renamed', 422);
        }

        $expenseTag->update($request->validated()); // code is never in validated(), so it stays stable
        $expenseTag->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Expense tag updated successfully', new ExpenseTagResource($expenseTag));
    }

    public function destroy(ExpenseTag $expenseTag): JsonResponse
    {
        if ($expenseTag->is_system) {
            return ApiResponse::custom('System tags cannot be deleted', 422);
        }

        $expenseTag->delete();

        return ApiResponse::delete('Expense tag deleted successfully');
    }

    public function syncCategories(ExpenseTagSyncCategoriesRequest $request, ExpenseTag $expenseTag): JsonResponse
    {
        $expenseTag->categories()->sync($request->validated()['category_ids']);
        $expenseTag->load(['categories:id,name']);

        return ApiResponse::update('Tag categories updated successfully', new ExpenseTagResource($expenseTag));
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'tag';
        $code = $base;
        $i = 2;
        while (ExpenseTag::withTrashed()->where('code', $code)->exists()) {
            $code = $base . '-' . $i++;
        }
        return $code;
    }
}
