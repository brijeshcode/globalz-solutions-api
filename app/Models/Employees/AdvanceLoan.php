<?php

namespace App\Models\Employees;

use App\Helpers\AccountsHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Traits\Authorable;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceLoan extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\AdvanceLoanFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable;

    protected $fillable = [
        'date',
        'prefix',
        'code',
        'employee_id',
        'account_id',
        'currency_id',
        'currency_rate',
        'amount',
        'amount_usd',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'currency_rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'employee_id',
        'currency_id',
        'amount',
        'amount_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // Scopes
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByPrefix($query, $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    // Helper Methods
    public function getAdvanceLoanCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.advanceLoan_code_start', 1000);
        $newValue = Setting::incrementValue('advanceLoans', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setAdvanceLoanCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($advanceLoan) {
            if (!$advanceLoan->code) {
                $advanceLoan->setAdvanceLoanCode();
            }

            $advanceLoan->prefix = 'ADL';
        });

        static::created(function ($advanceLoan) {
            // AdvanceLoan removes balance from account (money going out)
            AccountsHelper::removeBalance(Account::find($advanceLoan->account_id), $advanceLoan->amount_usd);
        });

        static::updated(function ($advanceLoan) {
            $original = $advanceLoan->getOriginal();

            // Update Account Balance
            // Case 1: Account changed
            if ($original['account_id'] != $advanceLoan->account_id) {
                // Add balance back to old account
                AccountsHelper::addBalance(Account::find($original['account_id']), $original['amount_usd']);
                // Remove balance from new account
                AccountsHelper::removeBalance(Account::find($advanceLoan->account_id), $advanceLoan->amount_usd);
            }
            // Case 2: Amount changed on same account
            elseif ($original['amount_usd'] != $advanceLoan->amount_usd) {
                $difference = $advanceLoan->amount_usd - $original['amount_usd'];
                // If amount increased, remove more; if decreased, add back
                AccountsHelper::removeBalance(Account::find($advanceLoan->account_id), $difference);
            }
        });

        static::deleted(function ($advanceLoan) {
            // Add balance back to account when advanceLoan is deleted
            AccountsHelper::addBalance(Account::find($advanceLoan->account_id), $advanceLoan->amount_usd);
        });
    }
}
