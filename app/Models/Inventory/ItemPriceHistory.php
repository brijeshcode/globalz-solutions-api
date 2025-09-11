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
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemPriceHistory extends Model
{
    use HasFactory, SoftDeletes, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'item_id',
        'price_usd',
        'average_waited_price',
        'latest_price',
        'effective_date',
        'source_type',
        'source_id',
        'note',
    ];

    protected $casts = [
        'price_usd' => 'decimal:4',
        'average_waited_price' => 'decimal:4',
        'latest_price' => 'decimal:4',
        'effective_date' => 'date',
    ];

    protected $searchable = [];

    protected $sortable = [
        'id',
        'item_id',
        'price_usd',
        'average_waited_price',
        'latest_price',
        'effective_date',
        'source_type',
        'source_id',
        'note',
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


    // Scopes
    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }


    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('effective_date', 'desc');
    }

    public function scopeOldestFirst($query)
    {
        return $query->orderBy('effective_date', 'asc');
    }

    public function scopeWithPriceChanges($query)
    {
        return $query->whereRaw('price_usd != latest_price');
    }

    public function scopeSignificantChanges($query, float $thresholdPercent = 10.0)
    {
        return $query->whereRaw('ABS((price_usd - latest_price) / latest_price * 100) >= ?', [$thresholdPercent]);
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

    public function getFormattedAveragePriceAttribute(): string
    {
        return '$' . number_format($this->average_waited_price ?? 0, 2);
    }

    public function getFormattedLatestPriceAttribute(): string
    {
        return '$' . number_format($this->latest_price ?? 0, 2);
    }

    public function getPriceChangeAttribute(): ?float
    {
        if (!$this->latest_price || $this->latest_price == 0) {
            return null;
        }
        return $this->price_usd - $this->latest_price;
    }

    public function getPriceChangePercentAttribute(): ?float
    {
        if (!$this->latest_price || $this->latest_price == 0) {
            return null;
        }
        return (($this->price_usd - $this->latest_price) / $this->latest_price) * 100;
    }

    public function getIsPriceIncreaseAttribute(): bool
    {
        return $this->getPriceChangeAttribute() > 0;
    }

    public function getIsPriceDecreaseAttribute(): bool
    {
        return $this->getPriceChangeAttribute() < 0;
    }

    public function getIsSignificantChangeAttribute(): bool
    {
        $changePercent = abs($this->getPriceChangePercentAttribute() ?? 0);
        return $changePercent >= 5.0; // Consider 5% or more as significant
    }

    public function getAgeInDaysAttribute(): int
    {
        if (!$this->effective_date) {
            return 0;
        }
        return $this->effective_date->diffInDays(now());
    }

    
    // Model Events
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($history) {
            // Ensure we have a valid effective date
            if (!$history->effective_date) {
                $history->effective_date = now()->toDateString();
            }
        });
    }
}
