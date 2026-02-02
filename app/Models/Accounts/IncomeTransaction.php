<?php

namespace App\Models\Accounts;

use App\Helpers\AccountsHelper;
use App\Models\Setting;
use App\Models\Setups\Accounts\IncomeCategory;
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
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeTransaction extends Model
{
    public const PREFIX = 'INC';
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, HasBooleanFilters,HasDocuments, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'income_category_id',
        'account_id',
        'subject',
        'amount',
        'amount_usd',
        'currency_id',
        'currency_rate',
        'order_number',
        'check_number',
        'bank_ref_number',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:8',
        'currency_rate' => 'decimal:4',
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
        'order_number',
        'check_number',
        'bank_ref_number',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    /**
     * Get the code attribute with "INC" prefix
     */
    public function getCodeAttribute($value): ?string
    {
        return $value ? self::PREFIX . $value : null;
    }

    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Setups\Generals\Currencies\Currency::class);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByIncomeCategory($query, $categoryId)
    {
        return $query->where('income_category_id', $categoryId);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Generate the next income transaction code from settings
     */
    public static function generateNextIncomeTransactionCode(): string
    {
        $defaultValue = config('app.income_transaction_code_start', 100);
        $nextNumber = Setting::getOrCreateCounter('income_transactions', 'code_counter', $defaultValue);
        return (string) $nextNumber;
    }

    /**
     * Reserve the next code number (increment counter)
     */
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.income_transaction_code_start', 100);
        $newValue = Setting::incrementValue('income_transactions', 'code_counter', 1, $defaultValue);
        return (string) ($newValue - 1);
    }

    /**
     * Check if a code is unique
     */
    public static function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = self::withTrashed()->where('code', $code);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Get the next suggested code for frontend display
     */
    public static function getNextSuggestedCode(): string
    {
        return self::generateNextIncomeTransactionCode();
    }

    /**
     * Validate and set code for new income transaction
     */
    public function setIncomeTransactionCode(?string $userCode = null): string
    {
        if ($userCode) {
            if (!self::isCodeUnique($userCode)) {
                throw new \InvalidArgumentException("Code '{$userCode}' is already in use.");
            }
            $this->code = $userCode;
            
            $suggestedCode = self::generateNextIncomeTransactionCode();
            if ($userCode === $suggestedCode) {
                $defaultValue = config('app.income_transaction_code_start', 100);
                Setting::incrementValue('income_transactions', 'code_counter', 1, $defaultValue);
            }
        } else {
            $this->code = self::reserveNextCode();
        }
        
        return $this->code;
    }

    /**
     * Handle code setting on model creation
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($incomeTransaction) {
            if (!$incomeTransaction->code) {
                $incomeTransaction->setIncomeTransactionCode();
            }
        });

        static::created(function ($incomeTransaction) {
            AccountsHelper::addBalance(Account::find($incomeTransaction->account_id), $incomeTransaction->amount);
        });

        static::updated(function ($incomeTransaction) {
            $original = $incomeTransaction->getOriginal();

            // If account changed, remove balance from old account and add to new account
            if ($original['account_id'] != $incomeTransaction->account_id) {
                AccountsHelper::removeBalance(Account::find($original['account_id']), $original['amount']);
                AccountsHelper::addBalance(Account::find($incomeTransaction->account_id), $incomeTransaction->amount);
            }
            // If amount changed on same account, adjust the difference
            elseif ($original['amount'] != $incomeTransaction->amount) {
                $difference = $incomeTransaction->amount - $original['amount'];
                AccountsHelper::addBalance(Account::find($incomeTransaction->account_id), $difference);
            }
        });

        static::deleted(function ($incomeTransaction) {
            AccountsHelper::removeBalance(Account::find($incomeTransaction->account_id), $incomeTransaction->amount);
        });
    }
}
