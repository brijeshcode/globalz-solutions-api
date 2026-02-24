<?php

namespace App\Models\Items;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListBulkUpdateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bulk_update_id',
        'price_list_item_id',
        'price_list_id',
        'item_id',
        'item_code',
        'item_description',
        'old_price',
        'new_price',
    ];

    protected $casts = [
        'bulk_update_id' => 'integer',
        'price_list_item_id' => 'integer',
        'price_list_id' => 'integer',
        'item_id' => 'integer',
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
    ];

    // Relationships
    public function bulkUpdate(): BelongsTo
    {
        return $this->belongsTo(PriceListBulkUpdate::class, 'bulk_update_id');
    }

    public function priceListItem(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
