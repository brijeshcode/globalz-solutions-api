<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\EmployeesStoreRequest;
use App\Http\Requests\Api\Employees\EmployeesUpdateRequest;
use App\Http\Resources\Api\Employees\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\Employee;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $employees = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Employees retrieved successfully',
            $employees,
            EmployeeResource::class
        );
    }

    public function store(EmployeesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = Employee::getCode();

        $employee = Employee::create($data);
        Employee::reserveNextCode();

        $employee->load(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name']);
        

        return ApiResponse::store(
            'Employee created successfully',
            new EmployeeResource($employee)
        );
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['department:id,name', 'user:id,name,email', 'zones:id,name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Employee retrieved successfully',
            new EmployeeResource($employee)
        );
    }

    public function update(EmployeesUpdateRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
        $employee->load(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Employee updated successfully',
            new EmployeeResource($employee)
        );
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return ApiResponse::delete('Employee deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Employee::onlyTrashed()
            ->with(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $employees = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed employees retrieved successfully',
            $employees,
            EmployeeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);
        $employee->restore();
        $employee->load(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Employee restored successfully',
            new EmployeeResource($employee)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);
        $employee->forceDelete();

        return ApiResponse::delete('Employee permanently deleted successfully');
    }
}
