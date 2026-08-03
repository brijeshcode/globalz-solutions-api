<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\AdvanceLoansStoreRequest;
use App\Http\Requests\Api\Employees\AdvanceLoansUpdateRequest;
use App\Http\Resources\Api\Employees\AdvanceLoanResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Models\Employees\Salary;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function employeeLoanSummary(Request $request): JsonResponse
    {
        $loanQuery = AdvanceLoan::select('employee_id', DB::raw('SUM(amount_usd) as loan_taken'))
            ->with('employee:id,name')
            ->groupBy('employee_id');

        if ($request->has('employee_id')) {
            $loanQuery->byEmployee($request->employee_id);
        }

        $loans = $loanQuery->get();

        $employeeIds = $loans->pluck('employee_id');

        $returnedByEmployee = Salary::select('employee_id', DB::raw('SUM(advance_payment) as loan_returned'))
            ->whereIn('employee_id', $employeeIds)
            ->where('advance_payment', '>', 0)
            ->groupBy('employee_id')
            ->pluck('loan_returned', 'employee_id');

        $summary = $loans->map(function ($loan) use ($returnedByEmployee) {
            $loanTaken = (float) $loan->loan_taken;
            $loanReturned = (float) ($returnedByEmployee[$loan->employee_id] ?? 0);

            return [
                'employee_id'   => $loan->employee_id,
                'employee_name' => $loan->employee->name,
                'loan_taken'    => $loanTaken,
                'loan_returned' => $loanReturned,
                'loan_balance'  => $loanTaken - $loanReturned,
            ];
        });

        return ApiResponse::show('Employee loan summary retrieved successfully', $summary);
    }

    public function employeeLoanFor(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|integer|exists:employees,id']);

        $employeeId = $request->employee_id;

        $loans = AdvanceLoan::where('employee_id', $employeeId)
            ->get(['date', 'prefix', 'code', 'amount_usd', 'note']);

        $salaries = Salary::where('employee_id', $employeeId)
            ->where('advance_payment', '>', 0)
            ->get(['date', 'prefix', 'code', 'advance_payment', 'note']);

        $transactions = collect();

        foreach ($loans as $loan) {
            $transactions->push([
                'date'   => $loan->date?->toDateString(),
                'code'   => $loan->prefix . $loan->code,
                'debit'  => (float) $loan->amount_usd,
                'credit' => 0.0,
                'note'   => $loan->note,
            ]);
        }

        foreach ($salaries as $salary) {
            $transactions->push([
                'date'   => $salary->date?->toDateString(),
                'code'   => $salary->prefix . $salary->code,
                'debit'  => 0.0,
                'credit' => (float) $salary->advance_payment,
                'note'   => $salary->note,
            ]);
        }

        

        $sorted = $transactions->sortBy('date')->values();

        $balance = 0.0;
        $ledger = $sorted->map(function ($row) use (&$balance) {
            $balance += $row['credit'] - $row['debit'];
            return array_merge($row, ['balance' => $balance]);
        })->sortByDesc('date')->values();

        return ApiResponse::show('Employee loan ledger retrieved successfully', $ledger);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->advanceLoanQuery($request);

        $employeeIds = (clone $query)->pluck('employee_id')->unique();

        $loanTaken = (clone $query)->sum('amount_usd');

        $loanReturned = Salary::whereIn('employee_id', $employeeIds)
            ->where('advance_payment', '>', 0)
            ->sum('advance_payment');

        $stats = [
            'total_advanceLoans' => (clone $query)->count(),
            'total_amount_usd'   => (float) $loanTaken,
            'loan_taken'         => (float) $loanTaken,
            'loan_returned'      => (float) $loanReturned,
            'loan_balance'       => (float) ($loanTaken - $loanReturned),
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
