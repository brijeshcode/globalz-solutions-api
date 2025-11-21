<?php

namespace App\Models\Items;

use App\Models\Setting;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTransfer extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, Searchable, Sortable;

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'shipping_status',
        'from_warehouse_id',
        'to_warehouse_id',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $searchable = [
        'code',
        'prefix',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'prefix',
        'shipping_status',
        'from_warehouse_id',
        'to_warehouse_id',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemTransferItem::class);
    }

    public function itemTransferItems(): HasMany
    {
        return $this->hasMany(ItemTransferItem::class);
    }

    // Scopes
    public function scopeByFromWarehouse($query, $warehouseId)
    {
        return $query->where('from_warehouse_id', $warehouseId);
    }

    public function scopeByToWarehouse($query, $warehouseId)
    {
        return $query->where('to_warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByShippingStatus($query, $shippingStatus)
    {
        return $query->where('shipping_status', $shippingStatus);
    }

    // Accessors & Mutators
    public function getTotalItemsCountAttribute(): int
    {
        return $this->itemTransferItems()->count();
    }

    public function getItemTransferCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->itemTransferItems()->sum('quantity');
    }

    public function getHasItemsAttribute(): bool
    {
        return $this->itemTransferItems()->exists();
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $settingKey = 'item_transfers';
        $defaultValue = config('app.item_transfer_code_start', 1000);
        $newValue = Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setItemTransferCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($itemTransfer) {
            if (!$itemTransfer->code) {
                $itemTransfer->setItemTransferCode();
            }
            $itemTransfer->prefix = 'TRAN';
        });
    }
}
