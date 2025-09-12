<?php

namespace App\Models\Suppliers;

use App\Models\Items\Item;
use App\Models\Setups\Supplier;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierItemPrice extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'supplier_id',
        'item_id',
        'currency_id',
        'price',
        'price_usd',
        'currency_rate',
        'last_purchase_id',
        'last_purchase_date',
        'is_current',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'price_usd' => 'decimal:4',
        'currency_rate' => 'decimal:6',
        'last_purchase_date' => 'date',
        'is_current' => 'boolean',
    ];

    protected $searchable = [
        'note',
    ];

    protected $sortable = [
        'id',
        'supplier_id',
        'item_id',
        'currency_id',
        'price',
        'price_usd',
        'currency_rate',
        'last_purchase_date',
        'is_current',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'last_purchase_date';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lastPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'last_purchase_id');
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
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeBySupplierAndItem($query, $supplierId, $itemId)
    {
        return $query->where('supplier_id', $supplierId)
                    ->where('item_id', $itemId);
    }

    public function scopeCurrentPrices($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('last_purchase_date', [$startDate, $endDate]);
    }

    public function scopeBestPricesForItem($query, $itemId, $limit = 5)
    {
        return $query->where('item_id', $itemId)
                    ->where('is_current', true)
                    ->orderBy('price_usd', 'asc')
                    ->limit($limit);
    }

    // Accessors & Mutators
    public function getConvertedPriceUsdAttribute(): float
    {
        if ($this->currency_rate > 0) {
            return (float) ($this->price / $this->currency_rate);
        }
        return (float) $this->price_usd;
    }

    public function getIsLatestForSupplierItemAttribute(): bool
    {
        return $this->is_current;
    }

    public function getAgeInDaysAttribute(): int
    {
        if (!$this->last_purchase_date) {
            return 0;
        }
        return $this->last_purchase_date->diffInDays(now());
    }

    // Helper Methods (moved to SupplierItemPriceService)
    public function updatePriceUsd(?float $currencyRate = null): void
    {
        app(\App\Services\Suppliers\SupplierItemPriceService::class)->updatePriceUsd($this, $currencyRate);
    }

    public function makeCurrentForSupplierItem(): void
    {
        app(\App\Services\Suppliers\SupplierItemPriceService::class)::makeCurrent($this);
    }

    public function updateFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        app(\App\Services\Suppliers\SupplierItemPriceService::class)->updateFromPurchase($this, $purchase, $purchaseItem);
    }

    // Static helper methods (moved to SupplierItemPriceService)
    public static function getCurrentPriceForSupplierItem(int $supplierId, int $itemId): ?self
    {
        return app(\App\Services\Suppliers\SupplierItemPriceService::class)::getCurrentPrice($supplierId, $itemId);
    }

    public static function getBestCurrentPricesForItem(int $itemId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return app(\App\Services\Suppliers\SupplierItemPriceService::class)::getBestPricesForItem($itemId, $limit);
    }

    public static function createFromPurchaseItem(Purchase $purchase, PurchaseItem $purchaseItem): self
    {
        return app(\App\Services\Suppliers\SupplierItemPriceService::class)::createFromPurchase($purchase, $purchaseItem);
    }

    public static function updateOrCreateFromPurchaseItem(Purchase $purchase, PurchaseItem $purchaseItem): self
    {
        return app(\App\Services\Suppliers\SupplierItemPriceService::class)::updateOrCreateFromPurchase($purchase, $purchaseItem);
    }

    // Model Events
    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if ($model->is_current) {
                // Deactivate other current prices for this supplier-item combo
                static::where('supplier_id', $model->supplier_id)
                    ->where('item_id', $model->item_id)
                    ->update(['is_current' => false]);
            }
        });

        static::updating(function ($model) {
            if ($model->is_current && $model->isDirty('is_current')) {
                // Deactivate other current prices for this supplier-item combo
                static::where('supplier_id', $model->supplier_id)
                    ->where('item_id', $model->item_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_current' => false]);
            }
        });

        static::saving(function ($model) {
            // Auto-calculate price_usd if currency_rate is available
            if ($model->currency_rate > 0 && $model->price > 0) {
                app(\App\Services\Suppliers\SupplierItemPriceService::class)::updatePriceUsd($model);
            }
        });
    }
}
