<?php

namespace App\Http\Controllers\Api\Reports\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Reports\Customer\CustomerAgingReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\Sale;
use App\Traits\HasPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $this->loadLastPayments($paginated->getCollection());
        $this->loadLastInvoices($paginated->getCollection());

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
        $this->loadLastPayments($rows);
        $this->loadLastInvoices($rows);

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
        // Invoice side = approved sales + debit notes (debit note treated as an invoice).
        $saleMaxDate = '(
            SELECT MAX(s.date) FROM sales s
            WHERE s.customer_id = customers.id
              AND s.approved_by IS NOT NULL
              AND s.deleted_at  IS NULL
        )';

        $debitNoteMaxDate = "(
            SELECT MAX(dn.date) FROM customer_credit_debit_notes dn
            WHERE dn.customer_id = customers.id
              AND dn.type = 'debit'
              AND dn.deleted_at IS NULL
        )";

        // Payment side = approved payments + credit notes (credit note treated as a payment).
        $paymentMaxDate = '(
            SELECT MAX(cp.date) FROM customer_payments cp
            WHERE cp.customer_id = customers.id
              AND cp.approved_by IS NOT NULL
              AND cp.deleted_at  IS NULL
        )';

        $creditNoteMaxDate = "(
            SELECT MAX(cn.date) FROM customer_credit_debit_notes cn
            WHERE cn.customer_id = customers.id
              AND cn.type = 'credit'
              AND cn.deleted_at IS NULL
        )";

        // Combine the two dates, ignoring whichever side is NULL.
        $lastInvoiceSub = "COALESCE(GREATEST({$saleMaxDate}, {$debitNoteMaxDate}), {$saleMaxDate}, {$debitNoteMaxDate})";
        $lastPaymentSub = "COALESCE(GREATEST({$paymentMaxDate}, {$creditNoteMaxDate}), {$paymentMaxDate}, {$creditNoteMaxDate})";

        // USD amount of the most recent row across approved sales and debit notes.
        $lastInvoiceAmountSub = "(
            SELECT z.amount_usd FROM (
                SELECT s.customer_id, s.date, s.total_usd AS amount_usd
                FROM sales s
                WHERE s.approved_by IS NOT NULL AND s.deleted_at IS NULL
                UNION ALL
                SELECT dn.customer_id, dn.date, dn.amount_usd
                FROM customer_credit_debit_notes dn
                WHERE dn.type = 'debit' AND dn.deleted_at IS NULL
            ) z
            WHERE z.customer_id = customers.id
            ORDER BY z.date DESC
            LIMIT 1
        )";

        // USD amount of the most recent row across payments and credit notes.
        $lastPaymentAmountSub = "(
            SELECT y.amount_usd FROM (
                SELECT cp.customer_id, cp.date, cp.amount_usd
                FROM customer_payments cp
                WHERE cp.approved_by IS NOT NULL AND cp.deleted_at IS NULL
                UNION ALL
                SELECT cn.customer_id, cn.date, cn.amount_usd
                FROM customer_credit_debit_notes cn
                WHERE cn.type = 'credit' AND cn.deleted_at IS NULL
            ) y
            WHERE y.customer_id = customers.id
            ORDER BY y.date DESC
            LIMIT 1
        )";

        $query = Customer::query()
            ->select('customers.*')
            ->addSelect(DB::raw("{$lastInvoiceSub} as last_invoice_date"))
            ->addSelect(DB::raw("DATEDIFF(NOW(), {$lastInvoiceSub}) as invoice_age"))
            ->addSelect(DB::raw("{$lastInvoiceAmountSub} as last_invoice_amount"))
            ->addSelect(DB::raw("{$lastPaymentSub} as last_payment_date"))
            ->addSelect(DB::raw("CASE WHEN customers.current_balance BETWEEN -2 AND 2 THEN 0 ELSE DATEDIFF(NOW(), {$lastPaymentSub}) END as payment_age"))
            ->addSelect(DB::raw("{$lastPaymentAmountSub} as last_payment_amount"))
            ->addSelect(DB::raw('employees.name as salesperson_name'))
            ->leftJoin('employees', 'employees.id', '=', 'customers.salesperson_id')
            ->with('salesperson:id,code,name')
            // must have at least one approved invoice, approved payment, or a credit/debit note
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
                )->orWhereExists(fn ($s) => $s
                    ->select(DB::raw(1))
                    ->from('customer_credit_debit_notes')
                    ->whereColumn('customer_credit_debit_notes.customer_id', 'customers.id')
                    ->whereNull('customer_credit_debit_notes.deleted_at')
                );
            });

        // --- Filters ---

        if ($request->filled('customer_id')) {
            $query->where('customers.id', $request->integer('customer_id'));
        }

        if ($request->filled('salesperson_id')) {
            $query->where('customers.salesperson_id', $request->integer('salesperson_id'));
        }

        // Exclude customers of disabled salespersons unless explicitly requested
        // (mirrors CustomersController::customerQuery). Filters the already-joined
        // employees row, so no extra subquery is needed.
        if (!$request->boolean('include_disabled_salespersons')) {
            $query->where(function (Builder $q) {
                $q->whereNull('customers.salesperson_id')
                  ->orWhere('employees.is_active', true);
            });
        }

        if ($request->boolean('hide_zero_balance')) {
            $query->whereNotBetween('customers.current_balance', [-1, 1]);
        }

        if ($request->boolean('hide_small_balance')) {
            $query->whereNotBetween('customers.current_balance', [-5, 5]);
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

    private function loadLastInvoices(Collection $customers): void
    {
        $ids = $customers->pluck('id');

        $sales = Sale::whereIn('customer_id', $ids)
            ->whereNotNull('approved_by')
            ->whereNull('deleted_at')
            ->get(['id', 'customer_id', 'prefix', 'code', 'date', 'total', 'total_usd']);

        // Debit notes are shown as invoices — map their amount onto the invoice shape.
        $debitNotes = CustomerCreditDebitNote::debit()
            ->whereIn('customer_id', $ids)
            ->whereNull('deleted_at')
            ->get(['id', 'customer_id', 'prefix', 'code', 'date', 'amount', 'amount_usd'])
            ->each(function ($note) {
                $note->total     = $note->amount;
                $note->total_usd = $note->amount_usd;
            });

        // concat (not merge) so sale/note ids can't collide and overwrite each other.
        $invoices = $sales->concat($debitNotes)
            ->groupBy('customer_id')
            ->map(fn ($group) => $group->sortByDesc('date')->take(5)->values());

        $customers->each(function ($customer) use ($invoices) {
            $customer->setRelation('lastInvoices', $invoices->get($customer->id, collect()));
        });
    }

    private function loadLastPayments(Collection $customers): void
    {
        $ids = $customers->pluck('id');

        $payments = CustomerPayment::whereIn('customer_id', $ids)
            ->whereNotNull('approved_by')
            ->whereNull('deleted_at')
            ->get(['id', 'customer_id', 'prefix', 'code', 'date', 'amount', 'amount_usd']);

        // Credit notes are shown as payments — their amount columns already match.
        $creditNotes = CustomerCreditDebitNote::credit()
            ->whereIn('customer_id', $ids)
            ->whereNull('deleted_at')
            ->get(['id', 'customer_id', 'prefix', 'code', 'date', 'amount', 'amount_usd']);

        $allPayments = $payments->concat($creditNotes)
            ->groupBy('customer_id')
            ->map(fn ($group) => $group->sortByDesc('date')->take(5)->values());

        $customers->each(function ($customer) use ($allPayments) {
            $customer->setRelation('lastPayments', $allPayments->get($customer->id, collect()));
        });
    }


}
