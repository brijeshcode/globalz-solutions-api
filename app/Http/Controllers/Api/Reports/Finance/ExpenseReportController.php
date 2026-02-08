<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\Salary;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpenseReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $fromDate = $request->get('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->get('to_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $expenseReport = $this->getExpenseReport($fromDate, $toDate, false);
        $excludedCategoryReport = $this->getExpenseReport($fromDate, $toDate, true);
        $salaryReport = $this->getSalaryReport($fromDate, $toDate);

        return ApiResponse::send('Expense report retrieved successfully', 200, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'expense_report' => $expenseReport,
            'excluded_category_report' => $excludedCategoryReport,
            'salary_report' => $salaryReport,
        ]);
    }

    private function getExpenseReport(string $fromDate, string $toDate, bool $excludedOnly): array
    {
        // Get expense totals grouped by category at database level
        $query = ExpenseTransaction::query()
            ->join('expense_categories', 'expense_transactions.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('
                expense_categories.id as category_id,
                expense_categories.name as category_name,
                expense_categories.parent_id,
                SUM(expense_transactions.amount) as total,
                SUM(expense_transactions.amount_usd) as total_usd
            ')
            ->fromDate($fromDate)
            ->toDate($toDate)
            ->whereNull('expense_categories.deleted_at')
            ->groupBy(
                'expense_categories.id',
                'expense_categories.name',
                'expense_categories.parent_id'
            );

        // Filter by exclude_from_profit
        if ($excludedOnly) {
            $query->where('expense_categories.exclude_from_profit', true);
        } else {
            $query->where(function ($q) {
                $q->whereNull('expense_categories.exclude_from_profit')
                    ->orWhere('expense_categories.exclude_from_profit', false);
            });
        }

        $categories = $query->get();

        if ($categories->isEmpty()) {
            return [
                'categories' => [],
                'totals' => [
                    'total' => 0,
                    'total_usd' => 0,
                ],
            ];
        }

        // Get parent categories for orphan children
        $parentCategoryIds = $categories->pluck('parent_id')->filter()->unique()->toArray();
        $parentCategories = ExpenseCategory::whereIn('id', $parentCategoryIds)
            ->get()
            ->keyBy('id');

        // Build category totals keyed by category_id
        $categoryTotals = [];
        foreach ($categories as $category) {
            $categoryTotals[$category->category_id] = [
                'category_id' => $category->category_id,
                'name' => $category->category_name,
                'parent_id' => $category->parent_id,
                'total' => round((float) $category->total, 2),
                'total_usd' => round((float) $category->total_usd, 8),
            ];
        }

        // Separate parents (parent_id = null) and children (parent_id != null)
        $parents = [];
        $children = [];

        foreach ($categoryTotals as $categoryId => $category) {
            if (is_null($category['parent_id'])) {
                $parents[$categoryId] = $category;
            } else {
                $children[$categoryId] = $category;
            }
        }

        // Build result keyed by parent ID to prevent duplicates
        $resultByParent = [];

        // Step 1: Add all parent categories that have transactions
        foreach ($parents as $parentId => $parent) {
            $resultByParent[$parentId] = [
                'category_id' => $parent['category_id'],
                'name' => $parent['name'],
                'total' => $parent['total'],
                'total_usd' => $parent['total_usd'],
                'sub_category_total' => 0,
                'sub_category_total_usd' => 0,
                'sub_categories' => [],
            ];
        }

        // Step 2: Add all children to their parents
        foreach ($children as $child) {
            $parentId = $child['parent_id'];

            // If parent doesn't exist in result (has no transactions), create it from DB
            if (!isset($resultByParent[$parentId])) {
                $parent = $parentCategories->get($parentId);
                if ($parent) {
                    $resultByParent[$parentId] = [
                        'category_id' => $parent->id,
                        'name' => $parent->name,
                        'total' => 0,
                        'total_usd' => 0,
                        'sub_category_total' => 0,
                        'sub_category_total_usd' => 0,
                        'sub_categories' => [],
                    ];
                }
            }

            // Add child to parent's sub_categories
            if (isset($resultByParent[$parentId])) {
                $resultByParent[$parentId]['sub_categories'][] = [
                    'parent_id' => $child['parent_id'],
                    'category_id' => $child['category_id'],
                    'name' => $child['name'],
                    'total' => $child['total'],
                    'total_usd' => $child['total_usd'],
                ];
                $resultByParent[$parentId]['sub_category_total'] += $child['total'];
                $resultByParent[$parentId]['sub_category_total_usd'] += $child['total_usd'];
            }
        }

        // Step 3: Calculate combined totals and build final result
        $result = [];
        $grandTotal = 0;
        $grandTotalUsd = 0;

        foreach ($resultByParent as $parent) {
            $parent['sub_category_total'] = round($parent['sub_category_total'], 2);
            $parent['sub_category_total_usd'] = round($parent['sub_category_total_usd'], 8);
            $parent['combined_total'] = round($parent['total'] + $parent['sub_category_total'], 2);
            $parent['combined_total_usd'] = round($parent['total_usd'] + $parent['sub_category_total_usd'], 8);

            $result[] = $parent;
            $grandTotal += $parent['combined_total'];
            $grandTotalUsd += $parent['combined_total_usd'];
        }

        // Sort by name
        usort($result, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'categories' => $result,
            'totals' => [
                'total' => round($grandTotal, 2),
                'total_usd' => round($grandTotalUsd, 8),
            ],
        ];
    }

    private function getSalaryReport(string $fromDate, string $toDate): array
    {
        $salaries = Salary::query()
            ->join('employees', 'salaries.employee_id', '=', 'employees.id')
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                SUM(salaries.final_total) as total,
                SUM(salaries.amount_usd) as total_usd
            ')
            ->fromDate($fromDate)
            ->toDate($toDate)
            ->whereNull('employees.deleted_at')
            ->groupBy('employees.id', 'employees.name')
            ->orderBy('employees.name')
            ->get();

        $grandTotal = 0;
        $grandTotalUsd = 0;

        $employees = $salaries->map(function ($salary) use (&$grandTotal, &$grandTotalUsd) {
            $total = round((float) $salary->total, 2);
            $totalUsd = round((float) $salary->total_usd, 8);

            $grandTotal += $total;
            $grandTotalUsd += $totalUsd;

            return [
                'employee_id' => $salary->employee_id,
                'employee_name' => $salary->employee_name,
                'total' => $total,
                'total_usd' => $totalUsd,
            ];
        })->toArray();

        return [
            'employees' => $employees,
            'totals' => [
                'total' => round($grandTotal, 2),
                'total_usd' => round($grandTotalUsd, 8),
            ],
        ];
    }
}
