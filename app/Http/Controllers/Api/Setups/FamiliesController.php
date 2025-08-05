<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\FamiliesStoreRequest;
use App\Http\Requests\Api\Setups\FamiliesUpdateRequest;
use App\Http\Resources\Api\Setups\FamilyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Family;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamiliesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Family::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $families = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Families retrieved successfully',
            $families,
            FamilyResource::class
        );
    }

    public function store(FamiliesStoreRequest $request): JsonResponse
    {
        $family = Family::create($request->validated());
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Family created successfully',
            new FamilyResource($family)
        );
    }

    public function show(Family $family): JsonResponse
    {
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Family retrieved successfully',
            new FamilyResource($family)
        );
    }

    public function update(FamiliesUpdateRequest $request, Family $family): JsonResponse
    {
        $family->update($request->validated());
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Family updated successfully',
            new FamilyResource($family)
        );
    }

    public function destroy(Family $family): JsonResponse
    {
        $family->delete();

        return ApiResponse::delete('Family deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Family::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $families = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed families retrieved successfully',
            $families,
            FamilyResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $family = Family::onlyTrashed()->findOrFail($id);
        $family->restore();
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Family restored successfully',
            new FamilyResource($family)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $family = Family::onlyTrashed()->findOrFail($id);
        $family->forceDelete();

        return ApiResponse::delete('Family permanently deleted successfully');
    }
}