<?php

namespace App\Models\Employees;

use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCreditDebitNote extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\EmployeeCreditDebitNoteFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'prefix',
        'code',
        'type',
        'employee_id',
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
        'type',
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

    // Scopes
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeByPrefix($query, $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // Helper Methods
    public function getNoteCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    // Code Generation
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.employee_credit_debit_note_code_start', 1000);
        $newValue = Setting::incrementValue('employeeCreditDebitNotes', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setNoteCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($note) {
            if (!$note->code) {
                $note->setNoteCode();
            }
        });
    }
}
