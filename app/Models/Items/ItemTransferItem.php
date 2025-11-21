<?php

namespace App\Models\Items;

use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTransferItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'item_code',
        'item_transfer_id',
        'item_id',
        'quantity',
        'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'item_transfer_id',
        'item_id',
        'quantity',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'asc';

    // Relationships
    public function itemTransfer(): BelongsTo
    {
        return $this->belongsTo(ItemTransfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($itemTransferItem) {
            if (!$itemTransferItem->item_code && $itemTransferItem->item) {
                $itemTransferItem->item_code = $itemTransferItem->item->code;
            }
        });
    }
}
