<?php

namespace App\Models\Expenses;

use App\Helpers\AccountsHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateWithTime;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseTransaction extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, HasBooleanFilters,HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'date',
        'expense_category_id',
        'account_id',
        'subject',
        'amount',
        'order_number',
        'check_number',
        'bank_ref_number',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
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

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

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

    /**
     * Generate the next expense transaction code from settings
     */
    public static function generateNextExpenseTransactionCode(): string
    {
        $defaultValue = config('app.expense_transaction_code_start', 100);
        $nextNumber = Setting::getOrCreateCounter('expense_transactions', 'code_counter', $defaultValue);
        return (string) $nextNumber;
    }

    /**
     * Reserve the next code number (increment counter)
     */
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.expense_transaction_code_start', 100);
        $newValue = Setting::incrementValue('expense_transactions', 'code_counter', 1, $defaultValue);
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
        return self::generateNextExpenseTransactionCode();
    }

    /**
     * Validate and set code for new expense transaction
     */
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

    /**
     * Handle code setting on model creation
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($expenseTransaction) {
            if (!$expenseTransaction->code) {
                $expenseTransaction->setExpenseTransactionCode();
            }
        });

        static::created(function ($expenseTransaction) {
            AccountsHelper::removeBalance(Account::find($expenseTransaction->account_id), $expenseTransaction->amount);
        });

        static::updated(function ($expenseTransaction) {
            $original = $expenseTransaction->getOriginal();

            // If account changed, restore balance to old account and deduct from new account
            if ($original['account_id'] != $expenseTransaction->account_id) {
                AccountsHelper::addBalance(Account::find($original['account_id']), $original['amount']);
                AccountsHelper::removeBalance(Account::find($expenseTransaction->account_id), $expenseTransaction->amount);
            }
            // If amount changed on same account, adjust the difference
            elseif ($original['amount'] != $expenseTransaction->amount) {
                $difference = $expenseTransaction->amount - $original['amount'];
                AccountsHelper::removeBalance(Account::find($expenseTransaction->account_id), $difference);
            }
        });

        static::deleted(function ($expenseTransaction) {
            AccountsHelper::addBalance(Account::find($expenseTransaction->account_id), $expenseTransaction->amount);
        });
    }
}
