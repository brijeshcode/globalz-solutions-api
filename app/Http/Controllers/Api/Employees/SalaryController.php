<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\SalariesStoreRequest;
use App\Http\Requests\Api\Employees\SalariesUpdateRequest;
use App\Http\Resources\Api\Employees\SalaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\Salary;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->salaryQuery($request);

        $salaries = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Salaries retrieved successfully',
            $salaries,
            SalaryResource::class
        );
    }

    public function store(SalariesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $salary = Salary::create($data);

        $salary->load([
            'employee:id,name,code',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store('Salary created successfully', new SalaryResource($salary));
    }

    public function show(Salary $salary): JsonResponse
    {
        $salary->load([
            'employee:id,name,code,address,phone,mobile,email,is_active',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Salary retrieved successfully',
            new SalaryResource($salary)
        );
    }

    public function update(SalariesUpdateRequest $request, Salary $salary): JsonResponse
    {
        $salary->update($request->validated());

        $salary->load([
            'employee:id,name,code',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update('Salary updated successfully', new SalaryResource($salary));
    }

    public function destroy(Salary $salary): JsonResponse
    {
        if (!RoleHelper::isSuperAdmin()) {
            return ApiResponse::customError('Only super administrators can delete salaries', 403);
        }

        $salary->delete();

        return ApiResponse::delete('Salary deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Salary::onlyTrashed()
            ->with([
                'employee:id,name,code',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        if ($request->has('month')) {
            $query->byMonth($request->month);
        }

        if ($request->has('year')) {
            $query->byYear($request->year);
        }

        $salaries = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed salaries retrieved successfully',
            $salaries,
            SalaryResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $salary = Salary::onlyTrashed()->findOrFail($id);

        $salary->restore();

        $salary->load([
            'employee:id,name,code',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Salary restored successfully',
            new SalaryResource($salary)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $salary = Salary::onlyTrashed()->findOrFail($id);

        $salary->forceDelete();

        return ApiResponse::delete('Salary permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->salaryQuery($request);

        $stats = [
            'total_salaries' => (clone $query)->count(),
            'total_sub_total' => (clone $query)->sum('sub_total'),
            'total_advance_payment' => (clone $query)->sum('advance_payment'),
            'total_others' => (clone $query)->sum('others'),
            'total_final_total' => (clone $query)->sum('final_total'),
        ];

        return ApiResponse::show('Salary statistics retrieved successfully', $stats);
    }

    public function getPendingLoans(int $employeeId): JsonResponse
    {
        $loans = AdvanceLoan::byEmployee($employeeId)->sum('amount_usd');
        $paid = Salary::byEmployee($employeeId)->sum('advance_payment');

        $netDue = $loans - $paid;
        return ApiResponse::show('Salary statistics retrieved successfully', $netDue);

    }

    private function salaryQuery(Request $request)
    {
        $query = Salary::query()
            ->with([
                'employee:id,name,code',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('month') && $request->has('year')) {
            $query->byMonthYear($request->month, $request->year);
        } elseif ($request->has('month')) {
            $query->byMonth($request->month);
        } elseif ($request->has('year')) {
            $query->byYear($request->year);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('from_date') && $request->has('to_date')) {
            $query->byDateRange($request->from_date, $request->to_date);
        } elseif ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        } elseif ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        return $query;
    }

    public function mySalaries(Request $request): JsonResponse
    {
        $myEmployee = RoleHelper::getEmployee();
        
        if(! $myEmployee) {
            return ApiResponse::notFound('Employee Not found');
        }

        $query = $this->salaryQuery($request);

        $query->where('employee_id', $myEmployee->id);
        $salaries = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Salaries retrieved successfully',
            $salaries,
            SalaryResource::class
        );
    }

    public function mySalaryDetail(Salary $salary): JsonResponse
    {
        // git refresh
        $myEmployee = RoleHelper::getEmployee();

        if(! $myEmployee) {
            return ApiResponse::notFound('Employee Not found');
        }

        if($salary->employee_id != $myEmployee->id){
            return ApiResponse::unauthorized('Invalid Salary details');
        }

        $salary->load([
            'employee:id,name,code,address,phone,mobile,email,is_active',
        ]);

        return ApiResponse::show(
            'Salary retrieved successfully',
            new SalaryResource($salary)
        );
    }
}
