<?php

namespace App\Models\Expenses;

use App\Helpers\AccountsHelper;
use App\Models\Accounts\Account;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpensePayment extends Model
{
    /** Default prefix; can be overridden per-record to 'EPX'. */
    public const DEFAULT_PREFIX = 'EP';

    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, HasDateFilters;

    protected array $searchable = ['order_number', 'check_number', 'bank_ref_number', 'note'];

    protected string $defaultSortField     = 'date';
    protected string $defaultSortDirection = 'desc';

    protected $fillable = [
        'expense_transaction_id',
        'account_id',
        'prefix',
        'amount',
        'amount_usd',
        'currency_id',
        'currency_rate',
        'date',
        'note',
        'order_number',
        'check_number',
        'bank_ref_number',
    ];

    protected $casts = [
        'date'          => 'date',
        'amount'        => 'decimal:2',
        'amount_usd'    => 'decimal:8',
        'currency_rate' => 'decimal:4',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Virtual code derived from the stored prefix + id.
     * e.g. prefix=EP, id=2  →  EP2
     */
    public function getCodeAttribute(): ?string
    {
        return $this->id ? ($this->prefix ?? self::DEFAULT_PREFIX) . $this->id : null;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function expenseTransaction(): BelongsTo
    {
        return $this->belongsTo(ExpenseTransaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Setups\Generals\Currencies\Currency::class);
    }

    // ─── Boot hooks ───────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Apply default prefix when not explicitly set
        static::creating(function (self $payment) {
            if (!$payment->prefix) {
                $payment->prefix = self::DEFAULT_PREFIX;
            }
        });

        // Payment recorded → deduct from account, sync paid_amount on expense
        static::created(function (self $payment) {
            AccountsHelper::removeBalance(Account::find($payment->account_id), (float) $payment->amount);
            self::syncExpensePaidAmount($payment->expense_transaction_id);
        });

        // Payment edited → reverse old balance, apply new, re-sync
        static::updated(function (self $payment) {
            $original = $payment->getOriginal();

            if ($original['account_id'] != $payment->account_id || $original['amount'] != $payment->amount) {
                AccountsHelper::addBalance(Account::find($original['account_id']), (float) $original['amount']);
                AccountsHelper::removeBalance(Account::find($payment->account_id), (float) $payment->amount);
                self::syncExpensePaidAmount($payment->expense_transaction_id);
            }
        });

        // Payment soft-deleted → restore account balance, re-sync
        static::deleted(function (self $payment) {
            AccountsHelper::addBalance(Account::find($payment->account_id), (float) $payment->amount);
            self::syncExpensePaidAmount($payment->expense_transaction_id);
        });

        // Payment restored → re-deduct from account, re-sync
        static::restored(function (self $payment) {
            AccountsHelper::removeBalance(Account::find($payment->account_id), (float) $payment->amount);
            self::syncExpensePaidAmount($payment->expense_transaction_id);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Recalculate paid_amount + paid_amount_usd on the parent expense from the
     * live DB sum of all active (non-deleted) payments.
     * Uses updateQuietly so the expense.updated hook is not re-triggered.
     */
    public static function syncExpensePaidAmount(int $expenseId): void
    {
        $expense = ExpenseTransaction::withTrashed()->find($expenseId);
        if (!$expense) {
            return;
        }

        $totals = static::where('expense_transaction_id', $expenseId)
            ->selectRaw('COALESCE(SUM(amount), 0) as total_paid, COALESCE(SUM(amount_usd), 0) as total_paid_usd')
            ->first();

        $expense->updateQuietly([
            'paid_amount'     => $totals->total_paid,
            'paid_amount_usd' => $totals->total_paid_usd,
        ]);
    }
}
