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
        'item_id',
        'quantity',
        'cost_price',
        'price',
        'price_usd',
        'ttc_price',
        'ttc_price_usd',
        'discount_percent',
        'unit_discount_amount',
        'unit_discount_amount_usd',
        'discount_amount',
        'discount_amount_usd',
        'tax_percent',
        'tax_label',
        'tax_amount',
        'tax_amount_usd',
        'total_price',
        'total_price_usd',
        'unit_profit',
        'total_profit',
        'unit_volume_cbm',
        'unit_weight_kg',
        'total_volume_cbm',
        'total_weight_kg',
        'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:0',
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'ttc_price' => 'decimal:2',
        'ttc_price_usd' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_amount_usd'=> 'decimal:2',
        'discount_percent' => 'decimal:2',
        'unit_discount_amount' => 'decimal:2',
        'unit_discount_amount_usd' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_amount_usd' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_price_usd' => 'decimal:2',
        'unit_profit' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'unit_volume_cbm' => 'decimal:4',
        'unit_weight_kg' => 'decimal:4',
        'total_volume_cbm' => 'decimal:3',
        'total_weight_kg' => 'decimal:3',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'item_id',
        'quantity',
        'price',
        'total_price',
        'unit_profit',
        'total_profit',
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

        static::creating(function ($saleItem) {
            // Get tax label from item's tax code
            if ($saleItem->item_id) {
                $item = Item::with('taxCode')->find($saleItem->item_id);
                if ($item) {
                    // Set tax label from tax code name or 'No' if no tax
                    $saleItem->tax_label = ($saleItem->tax_percent == 0 || !$item->taxCode)
                        ? 'No'
                        : $item->taxCode->name;

                    // Set volume and weight from item
                    $saleItem->unit_volume_cbm = $item->volume ?? 0;
                    $saleItem->unit_weight_kg = $item->weight ?? 0;
                    $saleItem->total_volume_cbm = ($item->volume ?? 0) * $saleItem->quantity;
                    $saleItem->total_weight_kg = ($item->weight ?? 0) * $saleItem->quantity;
                }
            } else {
                $saleItem->tax_label = $saleItem->tax_percent == 0 ? 'No' : 'TVA';
            }

            $price = $saleItem->price - $saleItem->unit_discount_amount;
            $priceUsd = $saleItem->price_usd - $saleItem->unit_discount_amount_usd;
            $saleItem->tax_amount = $saleItem->tax_percent > 0 ? $price * ($saleItem->tax_percent / 100) : 0 ;
            $saleItem->tax_amount_usd = $saleItem->tax_percent > 0 ? $priceUsd * ($saleItem->tax_percent / 100) : 0 ;
        });

        static::created(function ($saleItem) {
            // Reduce inventory when sale item is created
            InventoryService::subtract(
                $saleItem->item_id,
                $saleItem->sale->warehouse_id,
                $saleItem->quantity,
                "Sale #{$saleItem->sale->code} - Item sold"
            );

            // Recalculate total tax amount on the sale
            $saleItem->sale->recalculateTotalTax();
        });

        static::updating(function ($saleItem) {
            // Recalculate tax label, volume and weight if item, quantity, or tax_percent changed
            if ($saleItem->isDirty(['item_id', 'quantity', 'tax_percent'])) {
                if ($saleItem->item_id) {
                    $item = Item::with('taxCode')->find($saleItem->item_id);
                    if ($item) {
                        // Update tax label from tax code name or 'No' if no tax
                        if ($saleItem->isDirty(['item_id', 'tax_percent'])) {
                            $saleItem->tax_label = ($saleItem->tax_percent == 0 || !$item->taxCode)
                                ? 'No'
                                : $item->taxCode->name;
                        }

                        // Update volume and weight if item or quantity changed
                        if ($saleItem->isDirty(['item_id', 'quantity'])) {
                            $saleItem->unit_volume_cbm = $item->volume ?? 0;
                            $saleItem->unit_weight_kg = $item->weight ?? 0;
                            $saleItem->total_volume_cbm = ($item->volume ?? 0) * $saleItem->quantity;
                            $saleItem->total_weight_kg = ($item->weight ?? 0) * $saleItem->quantity;
                        }
                    }
                } else {
                    $saleItem->tax_label = $saleItem->tax_percent == 0 ? 'No' : 'TVA';
                }
            }

            $price = $saleItem->price - $saleItem->unit_discount_amount;
            $priceUsd = $saleItem->price_usd - $saleItem->unit_discount_amount_usd;
            $saleItem->tax_amount = $saleItem->tax_percent > 0 ? $price * ($saleItem->tax_percent / 100) : 0;
            $saleItem->tax_amount_usd = $saleItem->tax_percent > 0 ? $priceUsd * ($saleItem->tax_percent / 100) : 0;
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

            // Recalculate total tax amount on the sale if tax-related fields changed
            if ($saleItem->isDirty(['tax_amount', 'tax_amount_usd', 'quantity'])) {
                $saleItem->sale->recalculateTotalTax();
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

                // Recalculate total tax amount on the sale
                $saleItem->sale->recalculateTotalTax();
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
