<?php

namespace App\Models\Items;

use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemOffer extends Model
{
    /** @use HasFactory<\Database\Factories\Items\ItemOfferFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateFilters, Sortable;

    protected $fillable = [
        'item_id',
        'free_item_id',
        'date',
        'validity_date',
        'minimum_quantity',
        'free_quantity',
        'usage_limit',
        'can_change_quantity',
        'allow_multiple',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'date'               => 'date',
        'validity_date'      => 'date',
        'minimum_quantity'   => 'integer',
        'free_quantity'      => 'integer',
        'usage_limit'        => 'integer',
        'used_count'         => 'integer',
        'can_change_quantity' => 'boolean',
        'allow_multiple'     => 'boolean',
        'is_active'          => 'boolean',
    ];

    // Relationships

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function freeItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'free_item_id');
    }

    // Scopes

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeValid(Builder $query): void
    {
        $query->where('validity_date', '>=', now()->toDateString())
              ->whereColumn('used_count', '<', 'usage_limit');
    }

    // Helpers

    public function isExpired(): bool
    {
        return $this->validity_date->isPast();
    }

    public function isUsageLimitReached(): bool
    {
        return $this->used_count >= $this->usage_limit;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && !$this->isExpired() && !$this->isUsageLimitReached();
    }
}
