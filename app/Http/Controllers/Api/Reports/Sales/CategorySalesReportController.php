<?php

namespace App\Http\Controllers\Api\Reports\Sales;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategorySalesReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $itemGroupId = $request->get('item_group_id');
        $itemCategoryId = $request->get('item_category_id');
        $warehouseId = $request->get('warehouse_id');
        $salesmanId = $request->get('salesman_id');
        $includeReturns = $request->boolean('include_returns', true);

        $reportData = $this->getCategorySalesData(
            $year,
            $itemGroupId,
            $itemCategoryId,
            $warehouseId,
            $salesmanId,
            $includeReturns
        );

        return ApiResponse::send('Category sales report retrieved successfully', 200, $reportData);
    }


    private function getCategorySalesData(
        int $year,
        ?int $itemGroupId,
        ?int $itemCategoryId,
        ?int $warehouseId,
        ?int $salesmanId,
        bool $includeReturns
    ): array {
        // Build sales query - only approved sales (approved_by IS NOT NULL) with USD amounts
        $salesQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
            ->selectRaw('
                COALESCE(item_categories.id, 0) as category_id,
                COALESCE(item_categories.name, "Uncategorized") as category_name,
                MONTH(sales.date) as month,
                YEAR(sales.date) as year,
                SUM(sale_items.total_price_usd) as total_sales,
                SUM(sale_items.total_profit) as total_profit,
                SUM(sales.discount_amount_usd) as total_sale_discount
            ')
            ->whereYear('sales.date', $year)
            ->whereNull('sales.deleted_at')
            ->whereNotNull('sales.approved_by')
            ->whereNull('sale_items.deleted_at');

        // Apply filters
        if ($itemGroupId) {
            $salesQuery->where('items.item_group_id', $itemGroupId);
        }

        if ($itemCategoryId) {
            $salesQuery->where('items.item_category_id', $itemCategoryId);
        }

        if ($warehouseId) {
            $salesQuery->where('sales.warehouse_id', $warehouseId);
        }

        if ($salesmanId) {
            $salesQuery->where('sales.salesperson_id', $salesmanId);
        }

        $salesQuery->groupBy('category_id', 'category_name', 'month', 'year')
            ->orderBy('category_name')
            ->orderBy('month');

        $salesData = $salesQuery->get();

        // Get returns data if needed - only received returns with USD amounts
        $returnsData = collect();
        if ($includeReturns) {
            $returnsQuery = DB::table('customer_return_items')
                ->join('customer_returns', 'customer_return_items.customer_return_id', '=', 'customer_returns.id')
                ->join('items', 'customer_return_items.item_id', '=', 'items.id')
                ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
                ->selectRaw('
                    COALESCE(item_categories.id, 0) as category_id,
                    COALESCE(item_categories.name, "Uncategorized") as category_name,
                    MONTH(customer_returns.date) as month,
                    YEAR(customer_returns.date) as year,
                    SUM(customer_return_items.total_price_usd) as total_returns
                ')
                ->whereYear('customer_returns.date', $year)
                ->whereNull('customer_returns.deleted_at')
                ->whereNotNull('customer_returns.return_received_by')
                ->whereNull('customer_return_items.deleted_at');

            // Apply same filters for returns
            if ($itemGroupId) {
                $returnsQuery->where('items.item_group_id', $itemGroupId);
            }

            if ($itemCategoryId) {
                $returnsQuery->where('items.item_category_id', $itemCategoryId);
            }

            if ($warehouseId) {
                $returnsQuery->where('customer_returns.warehouse_id', $warehouseId);
            }

            if ($salesmanId) {
                $returnsQuery->where('customer_returns.salesperson_id', $salesmanId);
            }

            $returnsQuery->groupBy('category_id', 'category_name', 'month', 'year');

            $returnsData = $returnsQuery->get()->keyBy(function ($item) {
                return $item->category_id . '_' . $item->month;
            });
        }

        // Group data by category and month
        $categorizedData = [];
        $monthsWithData = [];

        foreach ($salesData as $sale) {
            $categoryKey = $sale->category_id;
            $monthKey = $sale->category_id . '_' . $sale->month;

            // Initialize category if not exists
            if (!isset($categorizedData[$categoryKey])) {
                $categorizedData[$categoryKey] = [
                    'category_id' => $sale->category_id,
                    'category_name' => $sale->category_name,
                    'months_data' => []
                ];
            }

            // Track which months have data for this category
            if (!isset($monthsWithData[$categoryKey])) {
                $monthsWithData[$categoryKey] = [];
            }
            $monthsWithData[$categoryKey][$sale->month] = true;

            // Get returns data for this category and month
            $returns = $returnsData->get($monthKey);
            $totalReturns = $returns ? $returns->total_returns : 0;

            // Calculate metrics
            $netSales = $sale->total_sales - $totalReturns;
            $grossProfit = $sale->total_profit; // Use pre-calculated profit
            $profitPercentage = $this->calculateProfitPercentage($netSales, $grossProfit);

            // Store month data
            $categorizedData[$categoryKey]['months_data'][$sale->month] = [
                'month' => $sale->month,
                'month_name' => date('F', mktime(0, 0, 0, $sale->month, 1)),
                'year' => $sale->year,
                'total_sales' => round($sale->total_sales, 2),
                'total_returns' => round($totalReturns, 2),
                'net_sales' => round($netSales, 2),
                'gross_profit' => round($grossProfit, 2),
                'total_sale_discount' => round($sale->total_sale_discount, 2),
                'profit_percentage' => $profitPercentage
            ];
        }

        // Pad all categories with all 12 months and calculate totals
        $yearTotals = [
            'total_sales' => 0,
            'total_returns' => 0,
            'net_sales' => 0,
            'gross_profit' => 0,
            'total_sale_discount' => 0,
        ];

        foreach ($categorizedData as $categoryKey => &$category) {
            $months = [];
            $categoryTotals = [
                'total_sales' => 0,
                'total_returns' => 0,
                'net_sales' => 0,
                'gross_profit' => 0,
                'total_sale_discount' => 0,
            ];

            for ($month = 1; $month <= 12; $month++) {
                if (isset($category['months_data'][$month])) {
                    // Use existing data
                    $monthData = $category['months_data'][$month];
                    $months[] = $monthData;

                    // Add to category totals
                    $categoryTotals['total_sales'] += $monthData['total_sales'];
                    $categoryTotals['total_returns'] += $monthData['total_returns'];
                    $categoryTotals['net_sales'] += $monthData['net_sales'];
                    $categoryTotals['gross_profit'] += $monthData['gross_profit'];
                    $categoryTotals['total_sale_discount'] += $monthData['total_sale_discount'];
                } else {
                    // Fill with zeros
                    $months[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'year' => $year,
                        'total_sales' => 0.00,
                        'total_returns' => 0.00,
                        'net_sales' => 0.00,
                        'gross_profit' => 0.00,
                        'total_sale_discount' => 0.00,
                        'profit_percentage' => 0.00
                    ];
                }
            }

            // Calculate category profit percentage
            $categoryTotals['profit_percentage'] = $this->calculateProfitPercentage(
                $categoryTotals['net_sales'],
                $categoryTotals['gross_profit']
            );

            // Round category totals
            $categoryTotals = array_map(function($value) {
                return is_numeric($value) ? round($value, 2) : $value;
            }, $categoryTotals);

            $category['totals'] = $categoryTotals;
            $category['months'] = $months;
            unset($category['months_data']); // Remove temporary data

            // Add to year totals
            $yearTotals['total_sales'] += $categoryTotals['total_sales'];
            $yearTotals['total_returns'] += $categoryTotals['total_returns'];
            $yearTotals['net_sales'] += $categoryTotals['net_sales'];
            $yearTotals['gross_profit'] += $categoryTotals['gross_profit'];
            $yearTotals['total_sale_discount'] += $categoryTotals['total_sale_discount'];
        }

        // Calculate year profit percentage
        $yearTotals['profit_percentage'] = $this->calculateProfitPercentage(
            $yearTotals['net_sales'],
            $yearTotals['gross_profit']
        );

        // Round year totals
        $yearTotals = array_map(function($value) {
            return is_numeric($value) ? round($value, 2) : $value;
        }, $yearTotals);

        return [
            'categories' => array_values($categorizedData),
            'year_totals' => $yearTotals
        ];
    }

    /**
     * Calculate profit percentage
     */
    private function calculateProfitPercentage(float $netSales, float $profit): float
    {
        if ($netSales == 0) {
            return 0;
        }

        return round(($profit / $netSales) * 100, 2);
    }
}
