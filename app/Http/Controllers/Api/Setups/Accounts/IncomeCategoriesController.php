<?php

namespace App\Http\Controllers\Api\Setups\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Accounts\IncomeCategoriesStoreRequest;
use App\Http\Requests\Api\Setups\Accounts\IncomeCategoriesUpdateRequest;
use App\Http\Resources\Api\Setups\Accounts\IncomeCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Accounts\IncomeCategory;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; 

class IncomeCategoriesController extends Controller
{
    use HasPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IncomeCategory::query()
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
            $query->whereNull('parent_id')->with([
                'childrenRecursive.createdBy:id,name',
                'childrenRecursive.updatedBy:id,name'
            ]);
        }

        if ($request->boolean('roots_only')) {
            $query->whereNull('parent_id');
        }

        if ($request->has('depth')) {
            $depth = (int) $request->input('depth', 1);
            if ($depth > 0) {
                $with = [];
                $relation = 'children';
                for ($i = 0; $i < $depth; $i++) {
                    $with[] = $relation . '.createdBy:id,name';
                    $with[] = $relation . '.updatedBy:id,name';
                    $relation .= '.children';
                }
                $query->with($with);
            }
        }

        $incomeCategories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'income categories retrieved successfully',
            $incomeCategories,
            IncomeCategoryResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IncomeCategoriesStoreRequest $request): JsonResponse
    {
        $incomeCategory = IncomeCategory::create($request->validated());
        $incomeCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name']);

        return ApiResponse::store(
            'income category created successfully',
            new IncomeCategoryResource($incomeCategory)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(IncomeCategory $incomeCategory): JsonResponse
    {
        $incomeCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name', 'children']);

        return ApiResponse::show(
            'income category retrieved successfully',
            new IncomeCategoryResource($incomeCategory)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IncomeCategoriesUpdateRequest $request, IncomeCategory $incomeCategory): JsonResponse
    {
        $incomeCategory->update($request->validated());
        $incomeCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name']);

        return ApiResponse::update(
            'income category updated successfully',
            new IncomeCategoryResource($incomeCategory)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IncomeCategory $incomeCategory): JsonResponse
    {
        if ($incomeCategory->has_children) {
            return ApiResponse::custom('Cannot delete income category that has children', 422);
        }

        $incomeCategory->delete();

        return ApiResponse::delete('income category deleted successfully');
    }

    /**
     * Display root categories only.
     */
    public function roots(Request $request): JsonResponse
    {
        $query = IncomeCategory::rootCategories()
            ->with(['createdBy:id,name', 'updatedBy:id,name', 'children'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('with_children')) {
            $query->with('childrenRecursive');
        }

        $incomeCategories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Root income categories retrieved successfully',
            $incomeCategories,
            IncomeCategoryResource::class
        );
    }

    /**
     * Display children of a specific category.
     */
    public function children(IncomeCategory $incomeCategory, Request $request): JsonResponse
    {
        $query = $incomeCategory->children()
            ->with(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('recursive')) {
            $query->with('childrenRecursive');
        }

        $children = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Category children retrieved successfully',
            $children,
            IncomeCategoryResource::class
        );
    }

    /**
     * Display ancestors of a specific category.
     */
    public function ancestors(IncomeCategory $incomeCategory): JsonResponse
    {
        $ancestors = $incomeCategory->getAllAncestors();
        
        return ApiResponse::send(
            'Category ancestors retrieved successfully',
            IncomeCategoryResource::collection($ancestors),
            200
        );
    }

    /**
     * Display descendants of a specific category.
     */
    public function descendants(IncomeCategory $incomeCategory): JsonResponse
    {
        $descendants = $incomeCategory->getAllDescendants();
        
        return ApiResponse::send(
            'Category descendants retrieved successfully',
            IncomeCategoryResource::collection($descendants),
            200
        );
    }

    /**
     * Get category tree structure starting from a specific category.
     */
    public function tree(IncomeCategory $incomeCategory): JsonResponse
    {
        $incomeCategory->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'parent:id,name',
            'childrenRecursive.createdBy:id,name',
            'childrenRecursive.updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Category tree retrieved successfully',
            new IncomeCategoryResource($incomeCategory)
        );
    }

    /**
     * Move category to a different parent.
     */
    public function move(Request $request, IncomeCategory $incomeCategory): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:income_categories,id|different:' . $incomeCategory->id,
        ]);

        $newParentId = $request->input('parent_id');

        if ($newParentId) {
            $newParent = IncomeCategory::findOrFail($newParentId);
            
            if ($incomeCategory->getAllDescendants()->contains('id', $newParentId)) {
                return ApiResponse::custom('Cannot move category to its own descendant', 422);
            }
        }

        $incomeCategory->update(['parent_id' => $newParentId]);
        $incomeCategory->load(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name']);

        return ApiResponse::update(
            'Category moved successfully',
            new IncomeCategoryResource($incomeCategory)
        );
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = IncomeCategory::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name', 'parent:id,name'])
            ->searchable($request)
            ->sortable($request);

        $incomeCategories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed income categories retrieved successfully',
            $incomeCategories,
            IncomeCategoryResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $incomeCategory = IncomeCategory::onlyTrashed()->findOrFail($id);
        $incomeCategory->restore();

        return ApiResponse::send('income category restored successfully', 200);
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $incomeCategory = IncomeCategory::onlyTrashed()->findOrFail($id);
        $incomeCategory->forceDelete();

        return ApiResponse::send('income category permanently deleted', 200);
    }
}
