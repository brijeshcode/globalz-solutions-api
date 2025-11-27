<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\AllowancesStoreRequest;
use App\Http\Requests\Api\Employees\AllowancesUpdateRequest;
use App\Http\Resources\Api\Employees\AllowanceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\Allowance;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllowancesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->allowanceQuery($request);

        $allowances = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Allowances retrieved successfully',
            $allowances,
            AllowanceResource::class
        );
    }

    public function store(AllowancesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $allowance = Allowance::create($data);

        $allowance->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store('Allowance created successfully', new AllowanceResource($allowance));
    }

    public function show(Allowance $allowance): JsonResponse
    {
        $allowance->load([
            'employee:id,name,code,address,phone,mobile,email,is_active',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Allowance retrieved successfully',
            new AllowanceResource($allowance)
        );
    }

    public function update(AllowancesUpdateRequest $request, Allowance $allowance): JsonResponse
    {
        $allowance->update($request->validated());

        $allowance->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update('Allowance updated successfully', new AllowanceResource($allowance));
    }

    public function destroy(Allowance $allowance): JsonResponse
    {
        if (!RoleHelper::isSuperAdmin()) {
            return ApiResponse::customError('Only super administrators can delete allowances', 403);
        }

        $allowance->delete();

        return ApiResponse::delete('Allowance deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Allowance::onlyTrashed()
            ->with([
                'employee:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        $allowances = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed allowances retrieved successfully',
            $allowances,
            AllowanceResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $allowance = Allowance::onlyTrashed()->findOrFail($id);

        $allowance->restore();

        $allowance->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Allowance restored successfully',
            new AllowanceResource($allowance)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $allowance = Allowance::onlyTrashed()->findOrFail($id);

        $allowance->forceDelete();

        return ApiResponse::delete('Allowance permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->allowanceQuery($request);

        $stats = [
            'total_allowances' => (clone $query)->count(),
            'total_amount_usd' => (clone $query)->sum('amount_usd'),
        ];

        return ApiResponse::show('Allowance statistics retrieved successfully', $stats);
    }

    private function allowanceQuery(Request $request)
    {
        $query = Allowance::query()
            ->with([
                'employee:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
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
}
