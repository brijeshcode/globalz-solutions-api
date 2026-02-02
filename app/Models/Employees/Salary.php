<?php

namespace App\Models\Employees;

use App\Helpers\AccountsHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salary extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\SalaryFactory> */
    use HasFactory, Authorable, SoftDeletes, HasDateWithTime, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'prefix',
        'code',
        'employee_id',
        'account_id',
        'month',
        'base_salary',
        'year',
        'sub_total',
        'advance_payment',
        'others',
        'final_total',
        'amount_usd',
        'currency_id',
        'currency_rate',
        'others_note',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'month' => 'integer',
        'year' => 'integer',
        'sub_total' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'others' => 'decimal:2',
        'final_total' => 'decimal:2',
        'amount_usd' => 'decimal:8',
        'currency_rate' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'note',
        'others_note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'employee_id',
        'month',
        'year',
        'sub_total',
        'advance_payment',
        'others',
        'final_total',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Setups\Generals\Currencies\Currency::class);
    }

    // Scopes
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeByMonthYear($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
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
    public function getSalaryCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.salary_code_start', 1000);
        $newValue = Setting::incrementValue('salaries', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setSalaryCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($salary) {
            if (!$salary->code) {
                $salary->setSalaryCode();
            }

            $salary->prefix = 'SAL';
        });

        static::created(function ($salary) {
            // Salary payment removes balance from account (money going out)
            AccountsHelper::removeBalance(Account::find($salary->account_id), $salary->final_total);
        });

        static::updated(function ($salary) {
            $original = $salary->getOriginal();

            // Update Account Balance
            // Case 1: Account changed
            if ($original['account_id'] != $salary->account_id) {
                // Add balance back to old account
                AccountsHelper::addBalance(Account::find($original['account_id']), $original['final_total']);
                // Remove balance from new account
                AccountsHelper::removeBalance(Account::find($salary->account_id), $salary->final_total);
            }
            // Case 2: Amount changed on same account
            elseif ($original['final_total'] != $salary->final_total) {
                $difference = $salary->final_total - $original['final_total'];
                // If amount increased, remove more; if decreased, add back
                AccountsHelper::removeBalance(Account::find($salary->account_id), $difference);
            }
        });

        static::deleted(function ($salary) {
            // Add balance back to account when salary is deleted
            AccountsHelper::addBalance(Account::find($salary->account_id), $salary->final_total);
        });
    }
}
