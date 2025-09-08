<?php

namespace App\Models\Inventory;

use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    protected $searchable = [];

    protected $sortable = [
        'id',
        'warehouse_id',
        'item_id',
        'quantity',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'asc';

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Scopes
    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByWarehouseAndItem($query, $warehouseId, $itemId)
    {
        return $query->where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemId);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeLowStock($query, $threshold = null)
    {
        if ($threshold !== null) {
            return $query->where('quantity', '<=', $threshold)
                        ->where('quantity', '>', 0);
        }
        
        // Join with items to use their low_quantity_alert
        return $query->join('items', 'inventories.item_id', '=', 'items.id')
                    ->whereColumn('inventories.quantity', '<=', 'items.low_quantity_alert')
                    ->where('inventories.quantity', '>', 0)
                    ->whereNotNull('items.low_quantity_alert')
                    ->select('inventories.*');
    }

    public function scopeByQuantityRange($query, $minQuantity, $maxQuantity)
    {
        return $query->whereBetween('quantity', [$minQuantity, $maxQuantity]);
    }

    public function scopeNegativeQuantity($query)
    {
        return $query->where('quantity', '<', 0);
    }

    // Accessors & Mutators
    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity, 2);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->quantity > 0;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity <= 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        if (!$this->item || !$this->item->low_quantity_alert) {
            return false;
        }
        return $this->quantity <= $this->item->low_quantity_alert && $this->quantity > 0;
    }

    public function getIsNegativeAttribute(): bool
    {
        return $this->quantity < 0;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity < 0) {
            return 'negative';
        } elseif ($this->quantity == 0) {
            return 'out_of_stock';
        } elseif ($this->getIsLowStockAttribute()) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function getValueAttribute(): float
    {
        if (!$this->item) {
            return 0.0;
        }
        
        $currentCost = $this->item->getCurrentCostAttribute();
        return (float) ($this->quantity * $currentCost);
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($inventory) {
            // Ensure quantity is not null
            if ($inventory->quantity === null) {
                $inventory->quantity = 0;
            }
        });

        static::saved(function ($inventory) {
            // Future: Trigger inventory alerts, update statistics, etc.
            // This could also update item current_quantity if maintained at item level
        });
    }
}
