<?php

namespace App\Models\Suppliers;

use App\Models\Items\Item;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'item_code',
        'purchase_id',
        'item_id',
        'price',
        'quantity',
        'discount_percent',
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
        'price' => 'decimal:5',
        'quantity' => 'integer',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:5',
        'total_price' => 'decimal:5',
        'total_price_usd' => 'decimal:2',
        'total_shipping_usd' => 'decimal:2',
        'total_customs_usd' => 'decimal:2',
        'total_other_usd' => 'decimal:2',
        'final_total_cost_usd' => 'decimal:2',
        'cost_per_item_usd' => 'decimal:2',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'purchase_id',
        'item_id',
        'price',
        'quantity',
        'discount_percent',
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
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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

    // Scopes
    public function scopeByPurchase($query, $purchaseId)
    {
        return $query->where('purchase_id', $purchaseId);
    }

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
              ->orWhere('discount_amount', '>', 0);
        });
    }

    // Accessors & Mutators
    public function getNetPriceAttribute(): float
    {
        return (float) ($this->price - $this->discount_amount);
    }

    public function getDiscountPercentageFromAmountAttribute(): float
    {
        if ($this->price <= 0) return 0;
        return (float) (($this->discount_amount / $this->price) * 100);
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_percent > 0 || $this->discount_amount > 0;
    }

    public function getUnitCostUsdAttribute(): float
    {
        if ($this->quantity <= 0) return 0;
        return (float) ($this->cost_per_item_usd);
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($purchaseItem) {
            if (!$purchaseItem->item_code && $purchaseItem->item) {
                $purchaseItem->item_code = $purchaseItem->item->code;
            }
        });

    }
}
