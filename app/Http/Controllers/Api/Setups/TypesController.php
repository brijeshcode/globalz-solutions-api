<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\TypesStoreRequest;
use App\Http\Requests\Api\Setups\TypesUpdateRequest;
use App\Http\Resources\Api\Setups\TypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Type;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TypesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Type::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Types retrieved successfully',
            $types,
            TypeResource::class
        );
    }

    public function store(TypesStoreRequest $request): JsonResponse
    {
        $type = Type::create($request->validated());
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Type created successfully',
            new TypeResource($type)
        );
    }

    public function show(Type $type): JsonResponse
    {
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Type retrieved successfully',
            new TypeResource($type)
        );
    }

    public function update(TypesUpdateRequest $request, Type $type): JsonResponse
    {
        $type->update($request->validated());
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Type updated successfully',
            new TypeResource($type)
        );
    }

    public function destroy(Type $type): JsonResponse
    {
        $type->delete();

        return ApiResponse::delete('Type deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Type::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed types retrieved successfully',
            $types,
            TypeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $type = Type::onlyTrashed()->findOrFail($id);
        $type->restore();
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Type restored successfully',
            new TypeResource($type)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $type = Type::onlyTrashed()->findOrFail($id);
        $type->forceDelete();

        return ApiResponse::delete('Type permanently deleted successfully');
    }
}