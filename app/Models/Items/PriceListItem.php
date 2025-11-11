<?php

namespace App\Models\Items;

use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceListItem extends Model
{
    /** @use HasFactory<\Database\Factories\Items\PriceListItemFactory> */
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'item_code',
        'price_list_id',
        'item_id',
        'item_description',
        'sell_price',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'price_list_id' => 'integer',
        'item_id' => 'integer',
    ];

    protected $searchable = [
        'item_code',
        'item_description',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'item_id',
        'sell_price',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Scopes
    public function scopeByPriceList($query, $priceListId)
    {
        return $query->where('price_list_id', $priceListId);
    }

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

        static::creating(function ($priceListItem) {
            // Automatically populate item_description and item_code from item if available
            if ($priceListItem->item_id) {
                $item = Item::find($priceListItem->item_id);
                if ($item) {
                    $priceListItem->item_code = $priceListItem->item_code ?? $item->code;
                    $priceListItem->item_description = $priceListItem->item_description ?? $item->description;
                }
            }
        });

        static::created(function ($priceListItem) {
            // Update item_count on the price list
            $priceListItem->priceList->updateItemCount();
        });

        static::updating(function ($priceListItem) {
            // Update item_description and item_code if item_id changes
            if ($priceListItem->isDirty('item_id') && $priceListItem->item_id) {
                $item = Item::find($priceListItem->item_id);
                if ($item) {
                    $priceListItem->item_code = $item->code;
                    $priceListItem->item_description = $item->description;
                }
            }
        });

        static::deleted(function ($priceListItem) {
            // Update item_count on the price list when item is deleted
            if ($priceListItem->priceList) {
                $priceListItem->priceList->updateItemCount();
            }
        });
    }
}
