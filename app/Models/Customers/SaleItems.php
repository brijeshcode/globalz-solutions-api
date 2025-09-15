<?php

namespace App\Models\Customers;

use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItems extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'item_code',
        'sale_id',
        'supplier_id',
        'item_id',
        'quantity',
        'price',
        'ttc_price',
        'discount_percent',
        'unit_discount_amount',
        'discount_amount',
        'total_price',
        'total_price_usd',
        'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'price' => 'decimal:2',
        'ttc_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'unit_discount_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_price_usd' => 'decimal:2',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'supplier_id',
        'item_id',
        'quantity',
        'price',
        'total_price',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($saleItem) {
            // Reduce inventory when sale item is created
            InventoryService::subtract(
                $saleItem->item_id,
                $saleItem->sale->warehouse_id,
                $saleItem->quantity,
                "Sale #{$saleItem->sale->code} - Item sold"
            );
        });

        static::updated(function ($saleItem) {
            // Handle quantity changes on update
            if ($saleItem->isDirty('quantity')) {
                $oldQuantity = $saleItem->getOriginal('quantity');
                $newQuantity = $saleItem->quantity;
                $difference = $newQuantity - $oldQuantity;

                if ($difference > 0) {
                    // Quantity increased - reduce more inventory
                    InventoryService::subtract(
                        $saleItem->item_id,
                        $saleItem->sale->warehouse_id,
                        $difference,
                        "Sale #{$saleItem->sale->code} - Quantity increased"
                    );
                } elseif ($difference < 0) {
                    // Quantity decreased - restore inventory
                    InventoryService::add(
                        $saleItem->item_id,
                        $saleItem->sale->warehouse_id,
                        abs($difference),
                        "Sale #{$saleItem->sale->code} - Quantity reduced"
                    );
                }
            }
        });

        static::deleted(function ($saleItem) {
            // Restore inventory when sale item is deleted (soft delete)
            // Skip if being deleted as part of sale deletion (handled in Sale model)
            if (!$saleItem->relationLoaded('sale')) {
                $saleItem->load('sale');
            }

            // Only restore if the parent sale exists and is not being deleted
            if ($saleItem->sale && !$saleItem->sale->trashed()) {
                InventoryService::add(
                    $saleItem->item_id,
                    $saleItem->sale->warehouse_id,
                    $saleItem->quantity,
                    "Sale #{$saleItem->sale->code} - Item deleted/cancelled"
                );
            }
        });

        static::forceDeleted(function ($saleItem) {
            // Restore inventory when sale item is permanently deleted
            InventoryService::add(
                $saleItem->item_id,
                $saleItem->sale->warehouse_id,
                $saleItem->quantity,
                "Sale #{$saleItem->sale->code} - Item permanently deleted"
            );
        });
    }
}
