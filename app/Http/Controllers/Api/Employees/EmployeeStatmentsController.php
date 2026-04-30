<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\DataHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Models\Employees\Salary;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeStatmentsController extends Controller
{
    use HasPagination;

    public function employeeStatements(Request $request, Employee $employee): JsonResponse
    {
        $search = $request->get('search');

        $allTransactions = $this->getTransactions($request, $employee, $search);
        $stats = $this->calculateStats($allTransactions);

        if ($request->boolean('withPage')) {
            $paginatedTransactions = DataHelper::customPaginate(
                $allTransactions,
                $this->getPerPage($request),
                $request->get('page', 1),
                $request
            );

            return ApiResponse::paginated(
                'Employee statement retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index(
            'Employee statement retrieved successfully',
            $allTransactions,
            $stats
        );
    }

    public function statements(Request $request): JsonResponse
    {
        $employeeSearch = $request->get('employee');
        $noteSearch = $request->get('note');

        $query = Employee::query()
            ->where('code', $employeeSearch)
            ->orWhere('name', 'like', "%{$employeeSearch}%");

        $employee = $query->first();

        if (!$employee) {
            if ($request->boolean('withPage')) {
                return ApiResponse::paginated(
                    'Statement retrieved successfully',
                    DataHelper::emptyPaginate($this->getPerPage($request), $request)
                );
            }

            return ApiResponse::index('Statement retrieved successfully', []);
        }

        $allTransactions = $this->getTransactions($request, $employee, $noteSearch);
        $stats = $this->calculateStats($allTransactions);

        if ($request->boolean('withPage')) {
            $paginatedTransactions = DataHelper::customPaginate(
                $allTransactions,
                $this->getPerPage($request),
                $request->get('page', 1),
                $request
            );

            return ApiResponse::paginated(
                'Statements retrieved successfully',
                $paginatedTransactions,
                null,
                $stats
            );
        }

        return ApiResponse::index('Statements retrieved successfully', $allTransactions, $stats);
    }

    private function getTransactions(Request $request, Employee $employee, ?string $noteSearch = null)
    {
        $transactionType = $request->get('transaction_type');

        $allTransactions = collect();

        if (!$transactionType || $transactionType === 'credit_debit_note') {
            $allTransactions = $allTransactions->concat(
                $this->getCreditDebitNotes($request, $employee, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'advance_loan') {
            $allTransactions = $allTransactions->concat(
                $this->getAdvanceLoans($request, $employee, $noteSearch)
            );
        }

        if (!$transactionType || $transactionType === 'salary') {
            $allTransactions = $allTransactions->concat(
                $this->getSalaryAdvances($request, $employee, $noteSearch)
            );
        }

        $sortedByDateAsc = $allTransactions->sortBy('date')->values();

        $balance = 0;
        $transactionsWithBalance = $sortedByDateAsc->map(function ($transaction) use (&$balance) {
            $balance += $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $balance;
            return $transaction;
        });

        $sortDirection = $request->get('sort_direction', 'desc');
        if ($sortDirection === 'desc') {
            return $transactionsWithBalance->reverse()->values();
        }

        return $transactionsWithBalance->values();
    }

    private function getCreditDebitNotes(Request $request, Employee $employee, ?string $noteSearch = null)
    {
        $query = EmployeeCreditDebitNote::query()
            ->select('id', 'code', 'prefix', 'date', 'type', 'amount_usd', 'note', 'employee_id', 'created_at');

        $this->applyFilters($query, $request, $employee, $noteSearch);

        return $query->with('employee:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'prefix' => $item->prefix,
                'type' => $item->type === 'credit' ? 'Credit Note' : 'Debit Note',
                'date' => $item->date,
                'amount' => $item->type === 'credit' ? -$item->amount_usd : $item->amount_usd,
                'debit' => $item->type === 'debit' ? $item->amount_usd : 0,
                'credit' => $item->type === 'credit' ? $item->amount_usd : 0,
                'note' => $item->note,
                'employee' => [
                    'id' => $item->employee->id,
                    'code' => $item->employee->code,
                    'name' => $item->employee->name,
                ],
                'transaction_type' => 'credit_debit_note',
                'source_table' => 'employee_credit_debit_notes',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getAdvanceLoans(Request $request, Employee $employee, ?string $noteSearch = null)
    {
        $query = AdvanceLoan::query()
            ->select('id', 'code', 'prefix', 'date', 'amount_usd', 'note', 'employee_id', 'created_at');

        $this->applyFilters($query, $request, $employee, $noteSearch);

        return $query->with('employee:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'prefix' => $item->prefix,
                'type' => 'Advance Loan',
                'date' => $item->date,
                'amount' => $item->amount_usd,
                'debit' => $item->amount_usd,
                'credit' => 0,
                'note' => $item->note,
                'employee' => [
                    'id' => $item->employee->id,
                    'code' => $item->employee->code,
                    'name' => $item->employee->name,
                ],
                'transaction_type' => 'advance_loan',
                'source_table' => 'advance_loans',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function getSalaryAdvances(Request $request, Employee $employee, ?string $noteSearch = null)
    {
        $query = Salary::query()
            ->where('advance_payment', '>', 0)
            ->select('id', 'code', 'prefix', 'date', 'advance_payment', 'note', 'employee_id', 'created_at');

        $this->applyFilters($query, $request, $employee, $noteSearch);

        return $query->with('employee:id,code,name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->prefix . $item->code,
                'prefix' => $item->prefix,
                'type' => 'Salary Advance Settlement',
                'date' => $item->date,
                'amount' => -$item->advance_payment,
                'debit' => 0,
                'credit' => $item->advance_payment,
                'note' => $item->note,
                'employee' => [
                    'id' => $item->employee->id,
                    'code' => $item->employee->code,
                    'name' => $item->employee->name,
                ],
                'transaction_type' => 'salary',
                'source_table' => 'salaries',
                'timestamp' => $item->date->timestamp,
            ];
        });
    }

    private function calculateStats($transactions)
    {
        $totalDebit = $transactions->sum('debit');
        $totalCredit = $transactions->sum('credit');

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $totalDebit - $totalCredit,
        ];
    }

    private function applyFilters($query, Request $request, Employee $employee, ?string $noteSearch = null)
    {
        $query->where('employee_id', $employee->id);

        if ($request->has('from_date')) {
            $query->fromDate($request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->toDate($request->get('to_date'));
        }

        if ($noteSearch) {
            $query->where(function ($q) use ($noteSearch) {
                $q->where('note', 'like', "%{$noteSearch}%")
                  ->orWhere('code', 'like', "%{$noteSearch}%")
                  ->orWhere('prefix', 'like', "%{$noteSearch}%");
            });
        }
    }
}
