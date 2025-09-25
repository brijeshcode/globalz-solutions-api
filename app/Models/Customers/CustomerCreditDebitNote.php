<?php

namespace App\Models\Customers;

use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCreditDebitNote extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'type',
        'customer_id',
        'currency_id',
        'currency_rate',
        'amount',
        'amount_usd',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'currency_rate' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'type',
        'customer_id',
        'amount',
        'amount_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Scopes
    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
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
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function getNoteCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(string $type = 'credit'): string
    {
        $settingKey = 'customer_credit_debit_notes';
        $defaultValue = config("app.customer_credit_debit_note_code_start", 1000);
        $newValue = Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setNoteCode(): string
    {
        return $this->code = self::reserveNextCode($this->type);
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
