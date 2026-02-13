<?php

namespace App\Models\Customers;

use App\Helpers\CustomersHelper;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use App\Traits\TracksActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCreditDebitNote extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, TracksActivity, HasDateFilters;

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

    protected $defaultSortField = 'date';
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

    // Activity logging 
    protected function getActivityLogAttributes(): array
    {
        return [
            'date',
            'type',
            'customer_id',
            'currency_id',
            'currency_rate',
            'amount',
            'amount_usd',
            'note',
        ];
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

        static::created(function ($note) {
            // Credit note increases customer balance (they paid us)
            // Debit note reduces customer balance (we paid them)
            if ($note->isCredit()) {
                CustomersHelper::addBalance(Customer::find($note->customer_id), $note->amount_usd);
            } else {
                CustomersHelper::removeBalance(Customer::find($note->customer_id), $note->amount_usd);
            }
        });

        static::updated(function ($note) {
            $original = $note->getOriginal();

            // Case 1: Customer changed
            if ($original['customer_id'] != $note->customer_id) {
                // Reverse the original entry
                if ($note->isCredit()) {
                    CustomersHelper::removeBalance(Customer::find($original['customer_id']), $original['amount_usd']);
                    CustomersHelper::addBalance(Customer::find($note->customer_id), $note->amount_usd);
                } else {
                    CustomersHelper::addBalance(Customer::find($original['customer_id']), $original['amount_usd']);
                    CustomersHelper::removeBalance(Customer::find($note->customer_id), $note->amount_usd);
                }
            }
            // Case 2: Type changed (credit to debit or vice versa)
            elseif ($original['type'] != $note->type) {
                if ($note->isCredit()) {
                    // Was debit, now credit - reverse remove and do add
                    CustomersHelper::addBalance(Customer::find($note->customer_id), $original['amount_usd']);
                    CustomersHelper::addBalance(Customer::find($note->customer_id), $note->amount_usd);
                } else {
                    // Was credit, now debit - reverse add and do remove
                    CustomersHelper::removeBalance(Customer::find($note->customer_id), $original['amount_usd']);
                    CustomersHelper::removeBalance(Customer::find($note->customer_id), $note->amount_usd);
                }
            }
            // Case 3: Amount changed on same customer and same type
            elseif ($original['amount_usd'] != $note->amount_usd) {
                $difference = $note->amount_usd - $original['amount_usd'];
                if ($note->isCredit()) {
                    CustomersHelper::addBalance(Customer::find($note->customer_id), $difference);
                } else {
                    CustomersHelper::removeBalance(Customer::find($note->customer_id), $difference);
                }
            }
        });

        static::deleted(function ($note) {
            // Reverse the balance change
            if ($note->isCredit()) {
                CustomersHelper::removeBalance(Customer::find($note->customer_id), $note->amount_usd);
            } else {
                CustomersHelper::addBalance(Customer::find($note->customer_id), $note->amount_usd);
            }
        });
    }
}