<?php

namespace App\Models\Items;

use App\Models\Setting;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemAdjust extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, Searchable, Sortable, HasDateFilters;
    public const TYPE_ADD= 'Add';
    public const TYPE_REMOVE = 'Subtract';

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'type',
        'warehouse_id',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $searchable = [
        'code',
        'prefix',
        'type',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'prefix',
        'type',
        'warehouse_id',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemAdjustItem::class);
    }

    public function itemAdjustItems(): HasMany
    {
        return $this->hasMany(ItemAdjustItem::class);
    }

    // Scopes
    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // Accessors & Mutators
    public function getTotalItemsCountAttribute(): int
    {
        return $this->itemAdjustItems()->count();
    }

    public function getItemAdjustCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->itemAdjustItems()->sum('quantity');
    }

    public function getHasItemsAttribute(): bool
    {
        return $this->itemAdjustItems()->exists();
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $settingKey = 'item_adjusts';
        $defaultValue = config('app.item_adjust_code_start', 1000);
        $newValue = Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setItemAdjustCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($itemAdjust) {
            if (!$itemAdjust->code) {
                $itemAdjust->setItemAdjustCode();
            }
            $itemAdjust->prefix = 'ADJ';
        });
    }
}
