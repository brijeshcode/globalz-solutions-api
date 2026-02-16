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
                SUM(sale_items.total_net_sell_price_usd) as total_sales,
                SUM(sale_items.total_tax_amount_usd) as total_sale_tax,
                SUM(sale_items.total_profit) as total_profit,
                SUM(
                    CASE
                        WHEN sales.sub_total_usd > 0
                        THEN (sale_items.total_net_sell_price_usd / sales.sub_total_usd) * sales.discount_amount_usd
                        ELSE 0
                    END
                ) as total_sale_discount
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
                    SUM(customer_return_items.total_price_usd - (customer_return_items.tax_amount_usd * customer_return_items.quantity)) as total_returns,
                    SUM(customer_return_items.tax_amount_usd * customer_return_items.quantity) as total_return_tax,
                    SUM(customer_return_items.total_profit) as total_return_profit
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
            $totalReturnTax = $returns ? $returns->total_return_tax : 0;
            $totalReturnProfit = $returns ? $returns->total_return_profit : 0;

            // Calculate metrics
            $netSales = $sale->total_sales - $sale->total_sale_discount - $totalReturns;
            $netTax = $sale->total_sale_tax - $totalReturnTax;
            // Deduct invoice-level discount and return profit from sales profit
            $netProfit = $sale->total_profit - $sale->total_sale_discount - $totalReturnProfit;
            $profitPercentage = $this->calculateProfitPercentage($netSales, $netProfit);

            // Store month data
            $categorizedData[$categoryKey]['months_data'][$sale->month] = [
                'month' => $sale->month,
                'month_name' => date('F', mktime(0, 0, 0, $sale->month, 1)),
                'year' => $sale->year,
                'total_sales' => round($sale->total_sales, 2),
                'total_sale_tax' => round($sale->total_sale_tax, 2),
                'total_returns' => round($totalReturns, 2),
                'total_return_tax' => round($totalReturnTax, 2),
                'net_sales' => round($netSales, 2),
                'net_sale_tax' => round($netTax, 2),
                'sales_profit' => round($sale->total_profit, 2),
                'return_profit' => round($totalReturnProfit, 2),
                'net_profit' => round($netProfit, 2),
                'total_sale_discount' => round($sale->total_sale_discount, 2),
                'profit_percentage' => $profitPercentage
            ];
        }

        // Pad all categories with all 12 months and calculate totals
        $yearTotals = [
            'total_sales' => 0,
            'total_sale_tax' => 0,
            'total_returns' => 0,
            'total_return_tax' => 0,
            'net_sales' => 0,
            'net_sale_tax' => 0,
            'sales_profit' => 0,
            'return_profit' => 0,
            'net_profit' => 0,
            'total_sale_discount' => 0,
        ];

        foreach ($categorizedData as $categoryKey => &$category) {
            $months = [];
            $categoryTotals = [
                'total_sales' => 0,
                'total_sale_tax' => 0,
                'total_returns' => 0,
                'total_return_tax' => 0,
                'net_sales' => 0,
                'net_sale_tax' => 0,
                'sales_profit' => 0,
                'return_profit' => 0,
                'net_profit' => 0,
                'total_sale_discount' => 0,
            ];

            for ($month = 1; $month <= 12; $month++) {
                if (isset($category['months_data'][$month])) {
                    // Use existing data
                    $monthData = $category['months_data'][$month];
                    $months[] = $monthData;

                    // Add to category totals
                    $categoryTotals['total_sales'] += $monthData['total_sales'];
                    $categoryTotals['total_sale_tax'] += $monthData['total_sale_tax'];
                    $categoryTotals['total_returns'] += $monthData['total_returns'];
                    $categoryTotals['total_return_tax'] += $monthData['total_return_tax'];
                    $categoryTotals['net_sales'] += $monthData['net_sales'];
                    $categoryTotals['net_sale_tax'] += $monthData['net_sale_tax'];
                    $categoryTotals['sales_profit'] += $monthData['sales_profit'];
                    $categoryTotals['return_profit'] += $monthData['return_profit'];
                    $categoryTotals['net_profit'] += $monthData['net_profit'];
                    $categoryTotals['total_sale_discount'] += $monthData['total_sale_discount'];
                } else {
                    // Fill with zeros
                    $months[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'year' => $year,
                        'total_sales' => 0.00,
                        'total_sale_tax' => 0.00,
                        'total_returns' => 0.00,
                        'total_return_tax' => 0.00,
                        'net_sales' => 0.00,
                        'net_sale_tax' => 0.00,
                        'sales_profit' => 0.00,
                        'return_profit' => 0.00,
                        'net_profit' => 0.00,
                        'total_sale_discount' => 0.00,
                        'profit_percentage' => 0.00
                    ];
                }
            }

            // Calculate category profit percentage
            $categoryTotals['profit_percentage'] = $this->calculateProfitPercentage(
                $categoryTotals['net_sales'],
                $categoryTotals['net_profit']
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
            $yearTotals['total_sale_tax'] += $categoryTotals['total_sale_tax'];
            $yearTotals['total_returns'] += $categoryTotals['total_returns'];
            $yearTotals['total_return_tax'] += $categoryTotals['total_return_tax'];
            $yearTotals['net_sales'] += $categoryTotals['net_sales'];
            $yearTotals['net_sale_tax'] += $categoryTotals['net_sale_tax'];
            $yearTotals['sales_profit'] += $categoryTotals['sales_profit'];
            $yearTotals['return_profit'] += $categoryTotals['return_profit'];
            $yearTotals['net_profit'] += $categoryTotals['net_profit'];
            $yearTotals['total_sale_discount'] += $categoryTotals['total_sale_discount'];
        }

        // Calculate year profit percentage
        $yearTotals['profit_percentage'] = $this->calculateProfitPercentage(
            $yearTotals['net_sales'],
            $yearTotals['net_profit']
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
        $cost = $netSales - $profit;
        if ($cost == 0) {
            return 0;
        }

        return round(($profit / $cost) * 100, 2);
    }

    /**
     * Get detailed sale items for a specific month and category
     */
    public function getSaleItemsDetail(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month');
        $itemCategoryId = $request->get('item_category_id');
        $itemGroupId = $request->get('item_group_id');
        $warehouseId = $request->get('warehouse_id');
        $salesmanId = $request->get('salesman_id');

        if (!$month) {
            return ApiResponse::send('Month is required', 422);
        }

        if (!$itemCategoryId && $itemCategoryId !== '0' && $itemCategoryId !== 0) {
            return ApiResponse::send('Item category is required', 422);
        }

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
            ->select([
                'sale_items.id',
                'sale_items.item_code',
                'items.description as item_description',
                'items.short_name as item_name',
                DB::raw('CONCAT(sales.prefix, sales.code) as invoice_code'),
                'sales.date',
                'sale_items.quantity',
                'sale_items.sale_id as sale_id',
                'sale_items.cost_price as cost_price',
                'sale_items.price_usd as unit_price',
                'sale_items.discount_percent',
                'sale_items.unit_discount_amount_usd as unit_discount',
                'sale_items.discount_amount_usd as total_discount',
                'sale_items.net_sell_price_usd as net_unit_price',
                'sale_items.total_net_sell_price_usd as total_price',
                'sale_items.total_tax_amount_usd as total_tax',
                'sale_items.unit_profit',
                'sale_items.total_profit',
                DB::raw('COALESCE(item_categories.id, 0) as category_id'),
                DB::raw('COALESCE(item_categories.name, "Uncategorized") as category_name'),
            ])
            ->whereYear('sales.date', $year)
            ->whereMonth('sales.date', $month)
            ->whereNull('sales.deleted_at')
            ->whereNotNull('sales.approved_by')
            ->whereNull('sale_items.deleted_at');

        // Apply category filter (0 means uncategorized)
        if ($itemCategoryId == 0) {
            $query->whereNull('items.item_category_id');
        } else {
            $query->where('items.item_category_id', $itemCategoryId);
        }

        if ($itemGroupId) {
            $query->where('items.item_group_id', $itemGroupId);
        }

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        if ($salesmanId) {
            $query->where('sales.salesperson_id', $salesmanId);
        }

        $saleItems = $query->orderBy('sales.date', 'desc')
            ->orderBy('invoice_code', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'total_quantity' => $saleItems->sum('quantity'),
            'total_discount' => round($saleItems->sum('total_discount'), 2),
            'total_sales' => round($saleItems->sum('total_price'), 2),
            'total_profit' => round($saleItems->sum('total_profit'), 2),
        ];

        return ApiResponse::send('Sale items detail retrieved successfully', 200, [
            'items' => $saleItems,
            'totals' => $totals,
        ]);
    }

    /**
     * Get detailed return items for a specific month and category
     */
    public function getReturnItemsDetail(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month');
        $itemCategoryId = $request->get('item_category_id');
        $itemGroupId = $request->get('item_group_id');
        $warehouseId = $request->get('warehouse_id');
        $salesmanId = $request->get('salesman_id');

        if (!$month) {
            return ApiResponse::send('Month is required', 422);
        }

        if (!$itemCategoryId && $itemCategoryId !== '0' && $itemCategoryId !== 0) {
            return ApiResponse::send('Item category is required', 422);
        }

        $query = DB::table('customer_return_items')
            ->join('customer_returns', 'customer_return_items.customer_return_id', '=', 'customer_returns.id')
            ->join('items', 'customer_return_items.item_id', '=', 'items.id')
            ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
            ->select([
                'customer_return_items.id',
                'customer_return_items.item_code',
                'items.description as item_description',
                'items.short_name as item_name',
                DB::raw('CONCAT(customer_returns.prefix, customer_returns.code) as return_code'),
                'customer_returns.date',
                'customer_return_items.quantity',
                'customer_return_items.price_usd as unit_price',
                'customer_return_items.discount_percent',
                'customer_return_items.unit_discount_amount_usd as unit_discount',
                'customer_return_items.discount_amount_usd as total_discount',
                'customer_return_items.ttc_price_usd as net_unit_price',
                DB::raw('(customer_return_items.total_price_usd - (customer_return_items.tax_amount_usd * customer_return_items.quantity)) as total_price'),
                DB::raw('(customer_return_items.tax_amount_usd * customer_return_items.quantity) as total_tax'),
                'customer_return_items.total_profit',
                DB::raw('COALESCE(item_categories.id, 0) as category_id'),
                DB::raw('COALESCE(item_categories.name, "Uncategorized") as category_name'),
            ])
            ->whereYear('customer_returns.date', $year)
            ->whereMonth('customer_returns.date', $month)
            ->whereNull('customer_returns.deleted_at')
            ->whereNotNull('customer_returns.return_received_by')
            ->whereNull('customer_return_items.deleted_at');

        // Apply category filter (0 means uncategorized)
        if ($itemCategoryId == 0) {
            $query->whereNull('items.item_category_id');
        } else {
            $query->where('items.item_category_id', $itemCategoryId);
        }

        if ($itemGroupId) {
            $query->where('items.item_group_id', $itemGroupId);
        }

        if ($warehouseId) {
            $query->where('customer_returns.warehouse_id', $warehouseId);
        }

        if ($salesmanId) {
            $query->where('customer_returns.salesperson_id', $salesmanId);
        }

        $returnItems = $query->orderBy('customer_returns.date', 'desc')
            ->orderBy('return_code', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'total_quantity' => $returnItems->sum('quantity'),
            'total_discount' => round($returnItems->sum('total_discount'), 2),
            'total_returns' => round($returnItems->sum('total_price'), 2),
            'total_profit' => round($returnItems->sum('total_profit'), 2),
        ];

        return ApiResponse::send('Return items detail retrieved successfully', 200, [
            'items' => $returnItems,
            'totals' => $totals,
        ]);
    }
}
