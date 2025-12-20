<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\EmployeesStoreRequest;
use App\Http\Requests\Api\Employees\EmployeesUpdateRequest;
use App\Http\Resources\Api\Employees\EmployeeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCommissionTarget;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'warehouses', 'updatedBy:id,name', 'employeeCommissionTargets.commissionTarget:id,name'])
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

        $employee = Employee::create($data);

        $employee->load(['department:id,name', 'user:id,name,email', 'createdBy:id,name', 'updatedBy:id,name']);
        

        return ApiResponse::store(
            'Employee created successfully',
            new EmployeeResource($employee)
        );
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['department:id,name', 'user:id,name,email', 'zones:id,name', 'createdBy:id,name', 'updatedBy:id,name', 'employeeCommissionTargets.commissionTarget:id,name']);

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

    public function assignWarehouse(Request $request, Employee $employee): JsonResponse
    {
        if(! $employee->isWarehouseDepartment()){
            return ApiResponse::customError('Employee '. $employee->name.' not belongs to Warehouse department', 422);
        }

        $validated = $request->validate([
          'warehouses' => 'required|array',
          'warehouses.*.warehouse_id' => 'required|exists:warehouses,id',
          'warehouses.*.is_primary' => 'boolean',
        ]);

        // Transform the data into the format expected by sync()
        $syncData = collect($validated['warehouses'])->mapWithKeys(function ($warehouse) {
            return [$warehouse['warehouse_id'] => ['is_primary' => $warehouse['is_primary'] ?? false]];
        })->toArray();

        $employee->warehouses()->sync($syncData);

        return ApiResponse::update(
            'Employee warehouses assigned successfully',
            new EmployeeResource($employee->load('warehouses'))
        );
    }

    public function setCommissionTarget(Request $request): JsonResponse
    {
        foreach($request->commissions as $commission){
            EmployeeCommissionTarget::updateOrCreate([
                'employee_id' => $request->employee_id,
                'month' => $commission['month'],
                'year' => $request->year
            ],
            [
                'commission_target_id' => $commission['commission_target_id'],
                'note' => $commission['note'],
            ]);
        }

        return ApiResponse::store('commission set completed successfull');
    }

    public function getEmployeeCommissionTarget(Request $request): JsonResponse
    {
        $query = EmployeeCommissionTarget::query()->with('commissionTarget:id,name')
            ->where('employee_id', $request->employee_id);

        $year = date('Y');
        
        if ($request->has('year')) {
            $year = $request->year;
        }

        if ($request->has('month')) {
            $query->whereMonth($request->month);
        }
         
        $query->whereYear($year);
        $commissions = $query->get();

        return ApiResponse::index('commissions targets', $commissions);
    }
}
