<?php

namespace App\Models\Suppliers;

use App\Models\Items\Item;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'item_code',
        'purchase_return_id',
        'item_id',
        'price',
        'quantity',
        'discount_percent',
        'unit_discount_amount',
        'discount_amount',
        'total_price',
        'total_price_usd',
        'total_shipping_usd',
        'total_customs_usd',
        'total_other_usd',
        'final_total_cost_usd',
        'cost_per_item_usd',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'quantity' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'unit_discount_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_price' => 'decimal:4',
        'total_price_usd' => 'decimal:4',
        'total_shipping_usd' => 'decimal:4',
        'total_customs_usd' => 'decimal:4',
        'total_other_usd' => 'decimal:4',
        'final_total_cost_usd' => 'decimal:4',
        'cost_per_item_usd' => 'decimal:4',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'purchase_return_id',
        'item_id',
        'price',
        'quantity',
        'discount_percent',
        'unit_discount_amount',
        'discount_amount',
        'total_price',
        'total_price_usd',
        'final_total_cost_usd',
        'cost_per_item_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'asc';

    // Relationships
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors

    public function getNetPriceAttribute(): float
    {
        return (float) ($this->price - $this->discount_amount);
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_percent > 0 || $this->discount_amount > 0 || $this->unit_discount_amount > 0;
    }

    public function getUnitCostUsdAttribute(): float
    {
        if ($this->quantity <= 0) return 0;
        return (float) ($this->cost_per_item_usd);
    }

    // Scopes

    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByItemCode($query, $itemCode)
    {
        return $query->where('item_code', $itemCode);
    }

    public function scopeWithDiscounts($query)
    {
        return $query->where(function($q) {
            $q->where('discount_percent', '>', 0)
              ->orWhere('discount_amount', '>', 0)
              ->orWhere('unit_discount_amount', '>', 0);
        });
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchaseReturnItem) {
            if (!$purchaseReturnItem->item_code && $purchaseReturnItem->item) {
                $purchaseReturnItem->item_code = $purchaseReturnItem->item->code;
            }
        });

        // Uncomment if you want automatic recalculation on save/delete
        // static::saved(function ($purchaseReturnItem) {
        //     if ($purchaseReturnItem->purchaseReturn) {
        //         $purchaseReturnItem->purchaseReturn->recalculateFromItems();
        //     }
        // });

        // static::deleted(function ($purchaseReturnItem) {
        //     if ($purchaseReturnItem->purchaseReturn) {
        //         $purchaseReturnItem->purchaseReturn->recalculateFromItems();
        //     }
        // });
    }
}
