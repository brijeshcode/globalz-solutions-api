<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Employees\Commission\CommissionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionsCalculationController extends Controller
{
    public function __construct(private CommissionCalculator $calculator) {}

    /**
     * Admin-only: commission for any employee.
     * `month`/`year` are the period anchor; each rule's actual window depends on its `period`
     * (a monthly rule uses the month, a yearly rule uses the whole year).
     */
    public function getEmployeeCommission(Request $request): JsonResponse
    {
        if (! RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can access this endpoint', 403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'month'       => 'nullable|integer|min:1|max:12',
            'year'        => 'nullable|integer|min:2000|max:2100',
        ]);
        // `detailed` is read via $request->boolean() below, which parses "true"/"false"/"1"/"0"
        // from the query string — the `boolean` validation rule rejects the string "true".

        $data = $this->calculator->forEmployee(
            (int) $validated['employee_id'],
            (int) ($validated['month'] ?? date('m')),
            (int) ($validated['year'] ?? date('Y')),
            $request->boolean('detailed'),
        );

        return ApiResponse::show('Monthly commission calculated successfully', $data);
    }

    /**
     * Salesman-only: commission for the logged-in salesman.
     * `month`/`year` are the period anchor; each rule's actual window depends on its `period`.
     */
    public function getMyCommission(Request $request): JsonResponse
    {
        if (! RoleHelper::isSalesman()) {
            return ApiResponse::customError('Only salesmen can access this endpoint', 403);
        }

        $employee = RoleHelper::getSalesmanEmployee();
        if (! $employee) {
            return ApiResponse::customError('Employee record not found for current user', 404);
        }

        $validated = $request->validate([
            'month'    => 'nullable|integer|min:1|max:12',
            'year'     => 'nullable|integer|min:2000|max:2100',
        ]);
        // `detailed` is read via $request->boolean() below (parses "true"/"false"/"1"/"0").

        $data = $this->calculator->forEmployee(
            $employee->id,
            (int) ($validated['month'] ?? date('m')),
            (int) ($validated['year'] ?? date('Y')),
            $request->boolean('detailed'),
        );

        return ApiResponse::show('Monthly commission calculated successfully', $data);
    }
}
