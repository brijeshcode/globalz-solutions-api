<?php

namespace App\Models\Suppliers;

use App\Models\Items\Item;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
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
        'total_expense_usd',
        'final_total_cost_usd',
        'cost_per_item_usd',
        'note',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity' => 'integer',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'float',
        'total_price' => 'float',
        'total_price_usd' => 'float',
        'total_expense_usd' => 'float',
        'final_total_cost_usd' => 'float',
        'cost_per_item_usd' => 'float',
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
        'total_expense_usd',
        'final_total_cost_usd',
        'cost_per_item_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'asc';

    // Relationships
    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeByPurchase(Builder $query, int $purchaseId)
    {
        return $query->where('purchase_id', $purchaseId);
    }

    public function scopeByItem(Builder $query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByItemCode(Builder $query, string $itemCode)
    {
        return $query->where('item_code', $itemCode);
    }

    public function scopeWithDiscounts(Builder $query)
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
