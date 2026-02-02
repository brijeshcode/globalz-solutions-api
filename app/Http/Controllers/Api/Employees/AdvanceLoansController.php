<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\AdvanceLoansStoreRequest;
use App\Http\Requests\Api\Employees\AdvanceLoansUpdateRequest;
use App\Http\Resources\Api\Employees\AdvanceLoanResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\AdvanceLoan;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvanceLoansController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->advanceLoanQuery($request);

        $advanceLoans = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'AdvanceLoans retrieved successfully',
            $advanceLoans,
            AdvanceLoanResource::class
        );
    }

    public function store(AdvanceLoansStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $advanceLoan = AdvanceLoan::create($data);

        $advanceLoan->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store('AdvanceLoan created successfully', new AdvanceLoanResource($advanceLoan));
    }

    public function show(AdvanceLoan $advanceLoan): JsonResponse
    {
        $advanceLoan->load([
            'employee:id,name,code,address,phone,mobile,email,is_active',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'AdvanceLoan retrieved successfully',
            new AdvanceLoanResource($advanceLoan)
        );
    }

    public function update(AdvanceLoansUpdateRequest $request, AdvanceLoan $advanceLoan): JsonResponse
    {
        $advanceLoan->update($request->validated());

        $advanceLoan->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update('AdvanceLoan updated successfully', new AdvanceLoanResource($advanceLoan));
    }

    public function destroy(AdvanceLoan $advanceLoan): JsonResponse
    {
        if (!RoleHelper::isSuperAdmin()) {
            return ApiResponse::customError('Only super administrators can delete advanceLoans', 403);
        }

        $advanceLoan->delete();

        return ApiResponse::delete('AdvanceLoan deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = AdvanceLoan::onlyTrashed()
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

        $advanceLoans = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed advanceLoans retrieved successfully',
            $advanceLoans,
            AdvanceLoanResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $advanceLoan = AdvanceLoan::onlyTrashed()->findOrFail($id);

        $advanceLoan->restore();

        $advanceLoan->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'AdvanceLoan restored successfully',
            new AdvanceLoanResource($advanceLoan)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $advanceLoan = AdvanceLoan::onlyTrashed()->findOrFail($id);

        $advanceLoan->forceDelete();

        return ApiResponse::delete('AdvanceLoan permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->advanceLoanQuery($request);

        $stats = [
            'total_advanceLoans' => (clone $query)->count(),
            'total_amount_usd' => (clone $query)->sum('amount_usd'),
        ];

        return ApiResponse::show('AdvanceLoan statistics retrieved successfully', $stats);
    }

    private function advanceLoanQuery(Request $request)
    {
        $query = AdvanceLoan::query()
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

        
        if ($request->has('date_from')) {
            $query->fromDate($request->date_from);
        } 
        
        if ($request->has('date_to')) {
            $query->toDate( $request->date_to);
        }

        return $query;
    }
}
