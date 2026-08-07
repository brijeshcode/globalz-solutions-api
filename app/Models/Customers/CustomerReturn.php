<?php

namespace App\Models\Customers;

use App\Contracts\ModuleLockable;
use App\Models\Employees\Employee;
use App\Models\Setting;
use Carbon\CarbonInterface;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\TracksActivity;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturn extends Model implements ModuleLockable
{
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, TracksActivity, HasDateFilters;

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
        'subtotal_taxable_amount',
        'subtotal_taxable_amount_usd',
        'total_tax_amount',
        'total_tax_amount_usd',
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
        'subtotal_taxable_amount' => 'decimal:2',
        'subtotal_taxable_amount_usd' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_tax_amount_usd' => 'decimal:2',
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
    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'salesperson_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerReturnItem::class);
    }

    /**
     * Define which attributes to log for activity tracking
     */
    protected function getActivityLogAttributes(): array
    {
        return [
            'date',
            'currency_id',
            'warehouse_id',
            'currency_rate',
            'total',
            'total_usd',
            'subtotal_taxable_amount',
            'subtotal_taxable_amount_usd',
            'total_tax_amount',
            'total_tax_amount_usd',
            'total_volume_cbm',
            'total_weight_kg',
            'note',
        ];
    }

    // we will not log un-approved customer returns 
    protected function shouldSkipActivityLog(): bool
    {
        return is_null($this->approved_at);
    }

    // Scopes
    public function scopeApproved(Builder $query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopePending(Builder $query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeReceived(Builder $query)
    {
        return $query->whereNotNull('return_received_by');
    }

    public function scopeNotReceived(Builder $query)
    {
        return $query->whereNull('return_received_by');
    }

    public function scopeByCustomer(Builder $query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByCurrency(Builder $query, int $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByWarehouse(Builder $query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange(Builder $query, \DateTimeInterface|string $startDate, \DateTimeInterface|string $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode(Builder $query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByPrefix(Builder $query, string $prefix)
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

    // Module lock (see App\Contracts\ModuleLockable)
    public function moduleLockKey(): string
    {
        return is_null($this->approved_by) ? 'customer_return_order' : 'customer_return';
    }

    public function moduleLockDate(): ?CarbonInterface
    {
        return $this->date;
    }

    public function isModuleLockExempt(): bool
    {
        // Approved but not yet received returns are still in-flight.
        return !is_null($this->approved_by) && is_null($this->return_received_by);
    }
}
