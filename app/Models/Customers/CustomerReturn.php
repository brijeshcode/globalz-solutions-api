<?php

namespace App\Models\Customers;

use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturn extends Model
{
    use HasFactory, SoftDeletes, Authorable,HasDateWithTime, Searchable, Sortable;

    public const TAXPREFIX = 'RTN';
    public const TAXFREEPREFIX = 'RTX';

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'salesperson_id',
        'customer_id',
        'currency_id',
        'warehouse_id',
        'currency_rate',
        'total',
        'total_usd',
        'total_volume_cbm',
        'total_weight_kg',
        'approved_by',
        'approved_at',
        'approve_note',
        'return_received_by',
        'return_received_at',
        'return_received_note',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'approved_at' => 'datetime',
        'return_received_at' => 'datetime',
        'total' => 'decimal:2',
        'total_usd' => 'decimal:2',
        'total_volume_cbm' => 'decimal:4',
        'total_weight_kg' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'note',
        'approve_note',
        'return_received_note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'customer_id',
        'total',
        'total_usd',
        'approved_at',
        'return_received_at',
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

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'salesperson_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returnReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerReturnItem::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopePending($query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeReceived($query)
    {
        return $query->whereNotNull('return_received_by');
    }

    public function scopeNotReceived($query)
    {
        return $query->whereNull('return_received_by');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
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
    public function isApproved(): bool
    {
        return !is_null($this->approved_by);
    }

    public function isPending(): bool
    {
        return is_null($this->approved_by);
    }

    public function isReceived(): bool
    {
        return !is_null($this->return_received_by);
    }

    public function getReturnCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.customer_return_code_start', 1000);
        $newValue = Setting::incrementValue('customer_returns', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setReturnCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($return) {
            if (!$return->code) {
                $return->setReturnCode();
            }
        });
    }
}
