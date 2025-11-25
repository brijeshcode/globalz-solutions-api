<?php

namespace App\Models\Suppliers;

use App\Helpers\SuppliersHelper;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierCreditDebitNote extends Model
{
    /** @use HasFactory<\Database\Factories\Suppliers\SupplierCreditDebitNoteFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable;

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'type',
        'supplier_id',
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
        'supplier_id',
        'amount',
        'amount_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
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
        $settingKey = 'supplier_credit_debit_notes';
        $defaultValue = config("app.supplier_credit_debit_note_code_start", 1000);
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
            // Credit note reduces supplier balance (we paid them)
            // Debit note increases supplier balance (they owe us)
            if ($note->isCredit()) {
                SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $note->amount_usd);
            } else {
                SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $note->amount_usd);
            }
        });

        static::updated(function ($note) {
            $original = $note->getOriginal();

            // Case 1: Supplier changed
            if ($original['supplier_id'] != $note->supplier_id) {
                // Reverse the original entry
                if ($note->isCredit()) {
                    SuppliersHelper::addBalance(Supplier::find($original['supplier_id']), $original['amount_usd']);
                    SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $note->amount_usd);
                } else {
                    SuppliersHelper::removeBalance(Supplier::find($original['supplier_id']), $original['amount_usd']);
                    SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $note->amount_usd);
                }
            }
            // Case 2: Type changed (credit to debit or vice versa)
            elseif ($original['type'] != $note->type) {
                if ($note->isCredit()) {
                    // Was debit, now credit - reverse add and do remove
                    SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $original['amount_usd']);
                    SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $note->amount_usd);
                } else {
                    // Was credit, now debit - reverse remove and do add
                    SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $original['amount_usd']);
                    SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $note->amount_usd);
                }
            }
            // Case 3: Amount changed on same supplier and same type
            elseif ($original['amount_usd'] != $note->amount_usd) {
                $difference = $note->amount_usd - $original['amount_usd'];
                if ($note->isCredit()) {
                    SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $difference);
                } else {
                    SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $difference);
                }
            }
        });

        static::deleted(function ($note) {
            // Reverse the balance change
            if ($note->isCredit()) {
                SuppliersHelper::addBalance(Supplier::find($note->supplier_id), $note->amount_usd);
            } else {
                SuppliersHelper::removeBalance(Supplier::find($note->supplier_id), $note->amount_usd);
            }
        });
    }
}
