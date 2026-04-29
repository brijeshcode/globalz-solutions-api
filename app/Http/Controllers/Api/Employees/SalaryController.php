<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\SalariesStoreRequest;
use App\Http\Requests\Api\Employees\SalariesUpdateRequest;
use App\Http\Resources\Api\Employees\SalaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\CommissionTargetRule;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Models\Employees\EmployeeCommissionTarget;
use App\Models\Employees\Salary;
use App\Models\Employees\SalaryItem;
use App\Models\Setting;
use App\Traits\CalculatesCommissions;
use App\Traits\HasPagination;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class SalaryController extends Controller
{
    use CalculatesCommissions, HasPagination;

    public function index(Request $request): JsonResponse
    {
        // $this->backfillSalaryItems();
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

        // Calculate net salary and its USD equivalent
        $data['net_salary'] = ($data['base_salary'] ?? 0) + ($data['sub_total'] ?? 0) + ($data['others'] ?? 0);
        $data['net_salary_usd'] = CurrencyHelper::toUsd(
            $data['currency_id'],
            $data['net_salary'],
            $data['currency_rate']
        );

        $salary = Salary::create($data);

        $this->syncSalaryItems($salary);

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

            // Recalculate net salary USD with new currency
            $netSalary = ($data['base_salary'] ?? $salary->base_salary) + ($data['sub_total'] ?? $salary->sub_total) + ($data['others'] ?? $salary->others);
            $data['net_salary'] = $netSalary;
            $data['net_salary_usd'] = CurrencyHelper::toUsd(
                $data['currency_id'],
                $netSalary,
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

        // Recalculate net salary if any component changed
        if (isset($data['base_salary']) || isset($data['sub_total']) || isset($data['others'])) {
            $netSalary = ($data['base_salary'] ?? $salary->base_salary) + ($data['sub_total'] ?? $salary->sub_total) + ($data['others'] ?? $salary->others);
            $data['net_salary'] = $netSalary;
            $currencyId = $data['currency_id'] ?? $salary->currency_id;
            $currencyRate = $data['currency_rate'] ?? $salary->currency_rate;
            $data['net_salary_usd'] = CurrencyHelper::toUsd($currencyId, $netSalary, $currencyRate);
        }

        $salary->update($data);

        $this->syncSalaryItems($salary);

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
        if (!RoleHelper::canSuperAdmin()) {
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
            // 'total_salaries' => (clone $query)->count(),
            'total_commission_total' => (clone $query)->sum('sub_total'),
            // 'total_advance_payment' => (clone $query)->sum('advance_payment'),
            // 'total_others' => (clone $query)->sum('others'),
            // 'total_amount_usd' => (clone $query)->sum('amount_usd'),
            'total_net_salary_usd' => (clone $query)->sum('net_salary_usd'),
            // 'this_month_salaries' => (clone $query)->whereMonth('date', now()->month)
            //     ->whereYear('date', now()->year)
            //     ->count(),
            // 'this_month_net_salary_usd' => (clone $query)->whereMonth('date', now()->month)
            //     ->whereYear('date', now()->year)
            //     ->sum('net_salary_usd'),

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

    private function syncSalaryItems(Salary $salary): void
    {
        $month      = $salary->month;
        $year       = $salary->year;
        $employeeId = $salary->employee_id;
        $firstDay   = Carbon::create($year, $month, 1)->startOfDay();
        $lastDay    = $firstDay->copy()->endOfMonth()->endOfDay();

        $commissionTarget = EmployeeCommissionTarget::with('commissionTarget.rules')
            ->byEmployee($employeeId)
            ->byMonth($month)
            ->byYear($year)
            ->first();

        SalaryItem::where('salary_id', $salary->id)->delete();

        if (!$commissionTarget?->commissionTarget) {
            return;
        }

        $commissions = $this->calculateCommissions($employeeId, $firstDay, $lastDay, $commissionTarget);

        $now   = now();
        $items = [];
        foreach ($commissions as $sort => $comm) {
            $items[] = [
                'salary_id'  => $salary->id,
                'label'      => $comm['commission_label'],
                'value'      => $comm['commission_amount'],
                'sort_order' => $sort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($items)) {
            SalaryItem::insert($items);
        }
    }

    private function calculateCommissions(int $employeeId, Carbon $firstDay, Carbon $lastDay, EmployeeCommissionTarget $target): array
    {
        $rules = $target->commissionTarget->rules;
        if ($rules->isEmpty()) {
            return [];
        }

        $saleIncludeType    = CommissionTargetRule::INCLUDE_TYPE_OWN;
        $paymentIncludeType = CommissionTargetRule::INCLUDE_TYPE_OWN;

        foreach ($rules as $rule) {
            if ($rule->type === 'sale') {
                $saleIncludeType = $rule->include_type ?? CommissionTargetRule::INCLUDE_TYPE_OWN;
            } elseif ($rule->type === 'payment') {
                $paymentIncludeType = $rule->include_type ?? CommissionTargetRule::INCLUDE_TYPE_OWN;
            }
        }

        [$totalSales, $totalReturns, $totalPayments] = $this->getCommissionTotals(
            $employeeId, $firstDay, $lastDay, $saleIncludeType, $paymentIncludeType
        );

        return $rules->map(function (CommissionTargetRule $rule) use ($totalSales, $totalReturns, $totalPayments) {
            $amount = match ($rule->type) {
                'fuel'    => $this->calculateFuelCommission($rule, $totalSales, $totalPayments, $totalReturns),
                'sale'    => $this->calculateSaleCommission($rule, $totalSales),
                'payment' => $this->calculatePaymentCommission($rule, $totalPayments),
                default   => 0.0,
            };

            return [
                'commission_label'  => $rule->comission_label,
                'commission_amount' => (float) $amount,
            ];
        })->values()->all();
    }

    private function getCompanyDataForPdf(): array
    {
        $companyData = SettingsHelper::getGroup('company');

        if (!empty($companyData['logo'])) {
            $setting = Setting::where('group_name', 'company')->where('key_name', 'logo')->first();

            if ($setting && $setting->documents()->exists()) {
                $document = $setting->documents()->latest()->first();
                $filePath = $document->file_path;

                if (str_starts_with($filePath, 'public/')) {
                    $filePath = substr($filePath, 7);
                }

                $absolutePath = storage_path('app/public/' . $filePath);
                if (!file_exists($absolutePath)) {
                    $absolutePath = storage_path($filePath);
                }

                $companyData['logo'] = [
                    'path'   => $absolutePath,
                    'exists' => file_exists($absolutePath),
                ];
            }
        }

        return $companyData;
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

    public function downloadPdf(Salary $salary)
    {
        try {
            $salary->load([
                'employee:id,name,code',
                'currency:id,name,code,symbol,symbol_position',
                'items',
            ]);

            $company    = $this->getCompanyDataForPdf();
            $month      = $salary->month;
            $year       = $salary->year;
            $employeeId = $salary->employee_id;
            $firstDay   = Carbon::create($year, $month, 1)->startOfDay();
            $lastDay    = $firstDay->copy()->endOfMonth()->endOfDay();

            $creditNotes = EmployeeCreditDebitNote::query()
                ->credit()
                ->byEmployee($employeeId)
                ->byDateRange($firstDay, $lastDay)
                ->orderBy('date')
                ->get();

            $debitNotes = EmployeeCreditDebitNote::query()
                ->debit()
                ->byEmployee($employeeId)
                ->byDateRange($firstDay, $lastDay)
                ->orderBy('date')
                ->get();

            $advanceLoans = AdvanceLoan::query()
                ->byEmployee($employeeId)
                ->byDateRange($firstDay, $lastDay)
                ->orderBy('date')
                ->get();

            $commissionPlanName = EmployeeCommissionTarget::with('commissionTarget')
                ->byEmployee($employeeId)
                ->byMonth($month)
                ->byYear($year)
                ->first()
                ?->commissionTarget
                ?->name;

            $monthName = Carbon::create($year, $month, 1)->format('F Y');

            $html = view('pdfs.salary-payslip', [
                'salary'             => $salary,
                'company'            => $company,
                'creditNotes'        => $creditNotes,
                'debitNotes'         => $debitNotes,
                'advanceLoans'       => $advanceLoans,
                'commissionItems'    => $salary->items,
                'commissionPlanName' => $commissionPlanName,
                'monthName'          => $monthName,
            ])->render();

            $mpdf = new Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_left'   => 10,
                'margin_right'  => 10,
                'margin_top'    => 10,
                'margin_bottom' => 15,
                'margin_header' => 8,
                'margin_footer' => 8,
            ]);

            $salaryCode        = $salary->prefix . $salary->code;
            $mpdf->SetHTMLFooter('
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . $salaryCode . '</td>
                        <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                        <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                    </tr>
                </table>');

            $mpdf->WriteHTML($html);

            $filename = 'payslip-' . $salaryCode . '.pdf';

            return response()->streamDownload(function () use ($mpdf) {
                echo $mpdf->Output('', 'S');
            }, $filename, ['Content-Type' => 'application/pdf']);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function backfillSalaryItems(): JsonResponse
    {
        $salaries = Salary::whereDoesntHave('items')->get();

        $processed = 0;
        $skipped   = 0;
        $errors    = [];

        foreach ($salaries as $salary) {
            try {
                $this->syncSalaryItems($salary);
                $processed++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "{$salary->prefix}{$salary->code}: {$e->getMessage()}";
            }
        }

        return ApiResponse::show('Salary items backfill completed', [
            'total'     => $salaries->count(),
            'processed' => $processed,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ]);
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
