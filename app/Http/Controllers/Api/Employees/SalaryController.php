<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\SalariesStoreRequest;
use App\Http\Requests\Api\Employees\SalariesUpdateRequest;
use App\Http\Resources\Api\Employees\SalaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
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
        $this->updateExistingSalariesCurrency();
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

        // Fetch account to get currency details
        $account = Account::with('currency.activeRate')->findOrFail($data['account_id']);

        // Set currency_id from account
        $data['currency_id'] = $account->currency_id;

        // Get currency rate (use active rate or default to 1)
        $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;

        // Convert final_total to USD
        $data['amount_usd'] = CurrencyHelper::toUsd(
            $data['currency_id'],
            $data['final_total'],
            $data['currency_rate']
        );

        $salary = Salary::create($data);

        $salary->load([
            'employee:id,name,code',
            'account:id,name',
            'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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
        $data = $request->validated();

        // If account_id or final_total changed, recalculate currency fields
        if (isset($data['account_id']) && $data['account_id'] != $salary->account_id) {
            // Account changed - fetch new account's currency
            $account = Account::with('currency.activeRate')->findOrFail($data['account_id']);
            $data['currency_id'] = $account->currency_id;
            $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;

            // Convert final_total to USD
            $finalTotal = $data['final_total'] ?? $salary->final_total;
            $data['amount_usd'] = CurrencyHelper::toUsd(
                $data['currency_id'],
                $finalTotal,
                $data['currency_rate']
            );
        } elseif (isset($data['final_total']) && $data['final_total'] != $salary->final_total) {
            // Amount changed but same account - use existing currency
            $data['amount_usd'] = CurrencyHelper::toUsd(
                $salary->currency_id,
                $data['final_total'],
                $salary->currency_rate
            );
        }

        $salary->update($data);

        $salary->load([
            'employee:id,name,code',
            'account:id,name',
            'currency:id,name,code,symbol',
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
                'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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
            'total_amount_usd' => (clone $query)->sum('amount_usd'),
            'this_month_salaries' => (clone $query)->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count(),
            'this_month_amount_usd' => (clone $query)->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount_usd'),

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
                'currency:id,name,code,symbol',
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

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date);
        }

        if ($request->has('to_date')) {
            $query->toDate($request->to_date);
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
            'currency:id,name,code,symbol',
        ]);

        return ApiResponse::show(
            'Salary retrieved successfully',
            new SalaryResource($salary)
        );
    }

    /**
     * Update existing salaries with currency fields
     */
    public function updateExistingSalariesCurrency(): JsonResponse
    {
        $updated = 0;
        $failed = 0;
        $errors = [];

        // Get all salaries where currency_id is null
        $salaries = Salary::whereNull('currency_id')
            ->with('account.currency.activeRate')
            ->get();

        foreach ($salaries as $salary) {
            try {
                $account = $salary->account;

                if (!$account || !$account->currency_id) {
                    $failed++;
                    $errors[] = "Salary {$salary->prefix}{$salary->code}: Account has no currency";
                    continue;
                }

                // Set currency_id from account
                $currencyId = $account->currency_id;

                // Get currency rate (use active rate or default to 1)
                $currencyRate = $account->currency->activeRate->rate ?? 1;

                // Convert final_total to USD
                $amountUsd = CurrencyHelper::toUsd(
                    $currencyId,
                    $salary->final_total,
                    $currencyRate
                );

                // Update salary
                $salary->updateQuietly([
                    'currency_id' => $currencyId,
                    'currency_rate' => $currencyRate,
                    'amount_usd' => $amountUsd,
                ]);

                $updated++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Salary {$salary->prefix}{$salary->code}: " . $e->getMessage();
            }
        }

        $result = [
            'total' => $salaries->count(),
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];

        if ($failed > 0) {
            return ApiResponse::customError('Some salaries failed to update', 422, $result);
        }

        return ApiResponse::show('Salaries updated successfully', $result);
    }
}
