<?php

namespace App\Http\Controllers\Api\Reports\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Reports\Customer\CustomerAgingReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Traits\HasPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAgingReportController extends Controller
{
    use HasPagination;

    /**
     * Allowed sort fields and their actual DB column/alias
     */
    private const SORTABLE = [
        'customer_code'      => 'customers.code',
        'customer_name'      => 'customers.name',
        'balance'            => 'customers.current_balance',
        'last_invoice_date'  => 'last_invoice_date',
        'invoice_age'        => 'invoice_age',
        'last_payment_date'  => 'last_payment_date',
        'payment_age'        => 'payment_age',
        'salesperson'        => 'salesperson_name',
    ];

    private const DEFAULT_SORT     = 'invoice_age';
    private const DEFAULT_SORT_DIR = 'desc';

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildQuery($request);
        $this->applySort($query, $request);

        $paginated = $query->paginate($this->getPerPage($request));

        $stats = [
            'total_balance' => (float) $this->buildQuery($request)->sum('customers.current_balance'),
        ];

        return ApiResponse::paginated(
            'Customer aging report retrieved successfully',
            $paginated,
            CustomerAgingReportResource::class,
            $stats
        );
    }

    public function export(Request $request): JsonResponse
    {
        $query = $this->buildQuery($request);
        $this->applySort($query, $request);

        $rows = $query->get();

        return ApiResponse::show(
            'Customer aging report exported successfully',
            CustomerAgingReportResource::collection($rows)
        );
    }

    // -------------------------------------------------------------------------
    // Core query builder — shared by index, export, and stats
    // -------------------------------------------------------------------------

    private function buildQuery(Request $request): Builder
    {
        $lastInvoiceSub = '(
            SELECT MAX(s.date) FROM sales s
            WHERE s.customer_id = customers.id
              AND s.approved_by IS NOT NULL
              AND s.deleted_at  IS NULL
        )';

        $lastPaymentSub = '(
            SELECT MAX(cp.date) FROM customer_payments cp
            WHERE cp.customer_id = customers.id
              AND cp.approved_by IS NOT NULL
              AND cp.deleted_at  IS NULL
        )';

        $query = Customer::query()
            ->select('customers.*')
            ->addSelect(DB::raw("{$lastInvoiceSub} as last_invoice_date"))
            ->addSelect(DB::raw("DATEDIFF(NOW(), {$lastInvoiceSub}) as invoice_age"))
            ->addSelect(DB::raw("{$lastPaymentSub} as last_payment_date"))
            ->addSelect(DB::raw("CASE WHEN customers.current_balance BETWEEN -2 AND 2 THEN 0 ELSE DATEDIFF(NOW(), {$lastPaymentSub}) END as payment_age"))
            ->addSelect(DB::raw('employees.name as salesperson_name'))
            ->leftJoin('employees', 'employees.id', '=', 'customers.salesperson_id')
            ->with('salesperson:id,code,name')
            // must have at least one approved invoice OR one approved payment
            ->where(function (Builder $q) {
                $q->whereExists(fn ($s) => $s
                    ->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.customer_id', 'customers.id')
                    ->whereNotNull('sales.approved_by')
                    ->whereNull('sales.deleted_at')
                )->orWhereExists(fn ($s) => $s
                    ->select(DB::raw(1))
                    ->from('customer_payments')
                    ->whereColumn('customer_payments.customer_id', 'customers.id')
                    ->whereNotNull('customer_payments.approved_by')
                    ->whereNull('customer_payments.deleted_at')
                );
            });

        // --- Filters ---

        if ($request->filled('customer_id')) {
            $query->where('customers.id', $request->integer('customer_id'));
        }

        if ($request->filled('salesperson_id')) {
            $query->where('customers.salesperson_id', $request->integer('salesperson_id'));
        }

        if ($request->boolean('hide_zero_balance')) {
            $query->where('customers.current_balance', '!=', 0);
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    private function applySort(Builder $query, Request $request): void
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
