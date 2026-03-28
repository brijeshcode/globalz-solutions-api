<?php

namespace App\Models\Expenses;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseTransaction extends Model
{
    public const PREFIX = 'EXP';
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, HasBooleanFilters, HasDocuments, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'expense_month',
        'expense_category_id',
        'account_id',
        'subject',
        'amount',
        'paid_amount',
        'paid_amount_usd',
        'amount_usd',
        'currency_id',
        'currency_rate',
        'order_number',
        'check_number',
        'bank_ref_number',
        'note',
        'vat_amount',
        'vat_amount_usd',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_amount_usd' => 'decimal:8',
        'amount_usd' => 'decimal:8',
        'currency_rate' => 'decimal:4',
        'expense_month' => 'date:Y-m-01',
        'vat_amount' => 'decimal:2',
        'vat_amount_usd' => 'decimal:8',
    ];

    protected $searchable = [
        'code',
        'subject',
        'order_number',
        'check_number',
        'bank_ref_number',
        'note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'subject',
        'amount',
        'paid_amount',
        'order_number',
        'check_number',
        'bank_ref_number',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getCodeAttribute($value): ?string
    {
        return $value ? self::PREFIX . $value : null;
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->amount + (float) $this->vat_amount;
    }

    public function getTotalAmountUsdAttribute(): float
    {
        return (float) $this->amount_usd + (float) $this->vat_amount_usd;
    }

    /**
     * How much is still owed. Derived — never stored.
     */
    public function getDueAmountAttribute(): float
    {
        return max(0, $this->total_amount - (float) $this->paid_amount);
    }

    /**
     * Derived from paid_amount vs total_amount — no column stored.
     * Returns: 'unpaid' | 'partial' | 'paid'
     */
    public function getPaymentStatusAttribute(): string
    {
        $paid  = (float) $this->paid_amount;
        $total = $this->total_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    // ─── Mutators ─────────────────────────────────────────────────────────────

    public function setExpenseMonthAttribute($value): void
    {
        $this->attributes['expense_month'] = $value ? $value . '-01' : null;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Setups\Generals\Currencies\Currency::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByExpenseCategory($query, $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // ─── Code generation ──────────────────────────────────────────────────────

    public static function generateNextExpenseTransactionCode(): string
    {
        $defaultValue = config('app.expense_transaction_code_start', 100);
        $nextNumber = Setting::getOrCreateCounter('expense_transactions', 'code_counter', $defaultValue);
        return (string) $nextNumber;
    }

    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.expense_transaction_code_start', 100);
        $newValue = Setting::incrementValue('expense_transactions', 'code_counter', 1, $defaultValue);
        return (string) ($newValue - 1);
    }

    public static function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = self::withTrashed()->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    public static function getNextSuggestedCode(): string
    {
        return self::generateNextExpenseTransactionCode();
    }

    public function setExpenseTransactionCode(?string $userCode = null): string
    {
        if ($userCode) {
            if (!self::isCodeUnique($userCode)) {
                throw new \InvalidArgumentException("Code '{$userCode}' is already in use.");
            }
            $this->code = $userCode;

            $suggestedCode = self::generateNextExpenseTransactionCode();
            if ($userCode === $suggestedCode) {
                $defaultValue = config('app.expense_transaction_code_start', 100);
                Setting::incrementValue('expense_transactions', 'code_counter', 1, $defaultValue);
            }
        } else {
            $this->code = self::reserveNextCode();
        }

        return $this->code;
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $expenseTransaction) {
            if (!$expenseTransaction->code) {
                $expenseTransaction->setExpenseTransactionCode();
            }
        });

        static::deleting(function (self $expenseTransaction) {
            // Cascade soft-delete all payments; each payment's deleted hook restores its account balance.
            $expenseTransaction->payments()->each(fn ($p) => $p->delete());
        });
    }
}
