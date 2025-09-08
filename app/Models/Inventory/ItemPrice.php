<?php

namespace App\Models\Inventory;

use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPrice extends Model
{
    use HasFactory, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'item_id',
        'price_usd',
        'effective_date',
        'last_purchase_id',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'effective_date' => 'date',
    ];

    protected $searchable = [];

    protected $sortable = [
        'id',
        'item_id',
        'price_usd',
        'effective_date',
        'last_purchase_id',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'effective_date';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lastPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'last_purchase_id');
    }

    // Scopes
    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    public function scopeLatestPrices($query)
    {
        return $query->orderBy('effective_date', 'desc');
    }

    public function scopeByPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('price_usd', [$minPrice, $maxPrice]);
    }

    // Accessors & Mutators
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price_usd, 2);
    }

    public function getAgeInDaysAttribute(): int
    {
        if (!$this->effective_date) {
            return 0;
        }
        return $this->effective_date->diffInDays(now());
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->getAgeInDaysAttribute() <= 30; // Consider price recent if within 30 days
    }
    // Model Events
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($itemPrice) {
            // Ensure we have a valid effective date
            if (!$itemPrice->effective_date) {
                $itemPrice->effective_date = now()->toDateString();
            }
        });

        static::created(function ($itemPrice) {
            // Create price history record if price has changed
            $previousPrice = static::byItem($itemPrice->item_id)
                                  ->where('id', '!=', $itemPrice->id)
                                  ->orderBy('effective_date', 'desc')
                                  ->first();

            
        });
    }
}
