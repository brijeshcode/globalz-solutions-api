<?php

namespace App\Models\Accounts;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTransfer extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'date',
        'code',
        // prefix is set by database default, not fillable to prevent override
        'from_account_id',
        'to_account_id',
        'from_currency_id',
        'to_currency_id',
        'received_amount',
        'sent_amount',
        'currency_rate',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'received_amount' => 'decimal:2',
        'sent_amount' => 'decimal:2',
        'currency_rate' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'received_amount',
        'sent_amount',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $settingKey = 'account_transfers';
        $defaultValue = config('app.account_transfer_code_start', 1000);
        $newValue = \App\Models\Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setTransferCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    public function getTransferCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (!$transfer->code) {
                $transfer->setTransferCode();
            }
            $transfer->prefix = 'TRF';
        });
    }
}
