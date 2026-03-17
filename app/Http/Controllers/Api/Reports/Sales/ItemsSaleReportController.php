<?php

namespace App\Http\Controllers\Api\Reports\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Reports\Sales\ItemsSaleReportResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemsSaleReportController extends Controller
{
    private const SORTABLE = [
        'item_name'             => 'items.short_name',
        'item_description'      => 'items.description',
        'item_code'             => 'sale_items.item_code',
        'total_quantity'        => 'total_quantity',
        'total_sale_amount'     => 'total_sale_amount',
        'total_sale_amount_usd' => 'total_sale_amount_usd',
        'total_profit'          => 'total_profit',
        'profit_percent'        => 'profit_percent',
    ];

    private const DEFAULT_SORT     = 'total_sale_amount';
    private const DEFAULT_SORT_DIR = 'desc';

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
            ->leftJoin('item_families', 'items.item_family_id', '=', 'item_families.id')
            ->leftJoin('item_groups', 'items.item_group_id', '=', 'item_groups.id')
            ->leftJoin('item_types', 'items.item_type_id', '=', 'item_types.id')
            ->leftJoin('item_brands', 'items.item_brand_id', '=', 'item_brands.id')
            ->leftJoin('suppliers', 'items.supplier_id', '=', 'suppliers.id')
            ->whereNull('sales.deleted_at')
            ->whereNotNull('sales.approved_by')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('items.deleted_at')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('sales.date', '>=', $request->get('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('sales.date', '<=', $request->get('to_date')))
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('sales.warehouse_id', $request->get('warehouse_id')))
            ->when($request->filled('sale_type'), fn($q) => $q->where('sales.prefix', $request->get('sale_type')))
            ->when($request->filled('salesperson_id'), fn($q) => $q->where('sales.salesperson_id', $request->get('salesperson_id')))
            ->when($request->filled('item_id'), fn($q) => $q->where('sale_items.item_id', $request->get('item_id')))
            ->when($request->filled('item_category_id'), fn($q) => $q->where('items.item_category_id', $request->get('item_category_id')))
            ->when($request->filled('item_family_id'), fn($q) => $q->where('items.item_family_id', $request->get('item_family_id')))
            ->when($request->filled('item_group_id'), fn($q) => $q->where('items.item_group_id', $request->get('item_group_id')))
            ->when($request->filled('item_brand_id'), fn($q) => $q->where('items.item_brand_id', $request->get('item_brand_id')))
            ->when($request->filled('item_supplier_id'), fn($q) => $q->where('items.supplier_id', $request->get('item_supplier_id')))
            ->when($request->filled('item_type_id'), fn($q) => $q->where('items.item_type_id', $request->get('item_type_id')));

        $query->select([
                'sale_items.item_id',
                'sale_items.item_code',
                'items.short_name as item_name',
                'items.description as item_description',
                DB::raw('COALESCE(item_categories.name, NULL) as category_name'),
                DB::raw('COALESCE(item_families.name, NULL) as family_name'),
                DB::raw('COALESCE(item_groups.name, NULL) as group_name'),
                DB::raw('COALESCE(item_types.name, NULL) as type_name'),
                DB::raw('COALESCE(item_brands.name, NULL) as brand_name'),
                DB::raw('COALESCE(suppliers.name, NULL) as supplier_name'),
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_sale_amount'),
                DB::raw('SUM(sale_items.total_price_usd) as total_sale_amount_usd'),
                DB::raw('SUM(sale_items.total_profit) as total_profit'),
                DB::raw('ROUND((SUM(sale_items.total_profit) / NULLIF(SUM(sale_items.total_price), 0)) * 100, 2) as profit_percent'),
            ])
            ->groupBy([
                'sale_items.item_id',
                'sale_items.item_code',
                'items.short_name',
                'items.description',
                'item_categories.name',
                'item_families.name',
                'item_groups.name',
                'item_types.name',
                'item_brands.name',
                'suppliers.name',
            ]);

        if ($request->boolean('hide_zero_sale', true)) {
            $query->havingRaw('SUM(sale_items.quantity) > 0');
        }

        $this->applySort($query, $request);

        // Calculate totals before pagination
        $totalsQuery = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->selectRaw('
                SUM(total_quantity) as total_quantity,
                SUM(total_sale_amount) as total_sale_amount,
                SUM(total_sale_amount_usd) as total_sale_amount_usd,
                SUM(total_profit) as total_profit,
                ROUND((SUM(total_profit) / NULLIF(SUM(total_sale_amount), 0)) * 100, 2) as profit_percent
            ')
            ->first();

        $items = $query->paginate($perPage);

        $stats = [
            'total_quantity'        => (float) ($totalsQuery->total_quantity ?? 0),
            'total_sale_amount'     => round((float) ($totalsQuery->total_sale_amount ?? 0), 2),
            'total_sale_amount_usd' => round((float) ($totalsQuery->total_sale_amount_usd ?? 0), 2),
            'total_profit'          => round((float) ($totalsQuery->total_profit ?? 0), 2),
            'profit_percent'        => round((float) ($totalsQuery->profit_percent ?? 0), 2),
        ];

        return ApiResponse::paginated(
            'Items sale report retrieved successfully',
            $items,
            ItemsSaleReportResource::class,
            $stats
        );
    }

    private function applySort(\Illuminate\Database\Query\Builder $query, Request $request): void
    {
        $sortBy  = $request->input('sort_by', self::DEFAULT_SORT);
        $sortDir = strtolower($request->input('sort_direction', self::DEFAULT_SORT_DIR));

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = self::DEFAULT_SORT_DIR;
        }

        $column = self::SORTABLE[$sortBy] ?? self::SORTABLE[self::DEFAULT_SORT];

        $query->orderBy(DB::raw($column), $sortDir);
    }
}
