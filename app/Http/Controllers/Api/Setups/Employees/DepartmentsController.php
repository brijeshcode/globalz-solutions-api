<?php

namespace App\Http\Controllers\Api\Setups\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Employees\DepartmentsStoreRequest;
use App\Http\Requests\Api\Setups\Employees\DepartmentsUpdateRequest;
use App\Http\Resources\Api\Setups\Employees\DepartmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Employees\Department;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Department::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $departments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Departments retrieved successfully',
            $departments,
            DepartmentResource::class
        );
    }

    public function store(DepartmentsStoreRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());
        $department->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Department created successfully',
            new DepartmentResource($department)
        );
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Department retrieved successfully',
            new DepartmentResource($department)
        );
    }

    public function update(DepartmentsUpdateRequest $request, Department $department): JsonResponse
    {
        $department->update($request->validated());
        $department->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Department updated successfully',
            new DepartmentResource($department)
        );
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();

        return ApiResponse::delete('Department deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Department::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $departments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed department retrieved successfully',
            $departments,
            DepartmentResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $department = Department::onlyTrashed()->findOrFail($id);
        $department->restore();
        $department->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Department restored successfully',
            new DepartmentResource($department)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $department = Department::onlyTrashed()->findOrFail($id);
        $department->forceDelete();

        return ApiResponse::delete('Department permanently deleted successfully');
    }
}
