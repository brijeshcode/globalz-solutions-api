<?php

namespace App\Models\Items;

use App\Models\Inventory\Inventory;
use App\Models\Setting;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\ItemCategory;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemGroup;
use App\Models\Setups\ItemProfitMargin;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'code',
        'short_name',
        'description',
        'item_type_id',
        'item_family_id',
        'item_group_id',
        'item_category_id',
        'item_brand_id',
        'item_unit_id',
        'item_profit_margin_id',
        'supplier_id',
        'tax_code_id',
        'volume',
        'weight',
        'barcode',
        'base_cost',
        'base_sell',
        'starting_price',
        'starting_quantity',
        'low_quantity_alert',
        'cost_calculation',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'volume' => 'decimal:4',
        'weight' => 'decimal:4',
        'base_cost' => 'decimal:6',
        'base_sell' => 'decimal:6',
        'starting_price' => 'decimal:6',
        'starting_quantity' => 'decimal:6',
        'low_quantity_alert' => 'decimal:6',
    ];

    protected $searchable = [
        'code',
        'short_name',
        'description',
        'barcode',
        'notes',
    ];

    protected $sortable = [
        'id',
        'code',
        'short_name',
        'description',
        'item_type_id',
        'item_profit_margin_id',
        'item_family_id',
        'item_group_id',
        'item_category_id',
        'item_brand_id',
        'supplier_id',
        'base_cost',
        'base_sell',
        'starting_price',
        'starting_quantity',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Cost calculation method constants
    public const COST_WEIGHTED_AVERAGE = 'weighted_average';
    public const COST_LAST_COST = 'last_cost';

    public static function getCostCalculationMethods(): array
    {
        return [
            self::COST_WEIGHTED_AVERAGE,
            self::COST_LAST_COST,
        ];
    }

    // Relationships
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function itemFamily(): BelongsTo
    {
        return $this->belongsTo(ItemFamily::class);
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function itemBrand(): BelongsTo
    {
        return $this->belongsTo(ItemBrand::class);
    }

    public function itemUnit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class);
    }

    public function itemProfitMargin(): BelongsTo
    {
        return $this->belongsTo(ItemProfitMargin::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function itemPrice(): HasOne
    {
        return $this->hasOne(\App\Models\Inventory\ItemPrice::class);
    }

    // Accessors & Mutators
    public function getIsLowStockAttribute(): bool
    {
        if (!$this->low_quantity_alert) {
            return false;
        }
        return $this->total_inventory_quantity <= $this->low_quantity_alert;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('item_type_id', $typeId);
    }

    public function scopeByFamily($query, $familyId)
    {
        return $query->where('item_family_id', $familyId);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('item_group_id', $groupId);
    }

    public function scopeByProfitMargin($query, $profitMarginId)
    {
        return $query->where('item_profit_margin_id', $profitMarginId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('item_category_id', $categoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('item_brand_id', $brandId);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereNotNull('low_quantity_alert')
                     ->whereHas('inventories')
                     ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventories WHERE inventories.item_id = items.id) <= items.low_quantity_alert');
    }

    public function scopeByBarcode($query, $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // Helper Methods
    public function isCostCalculatedByWeightedAverage(): bool
    {
        return $this->cost_calculation === self::COST_WEIGHTED_AVERAGE;
    }

    public function isCostCalculatedByLastCost(): bool
    {
        return $this->cost_calculation === self::COST_LAST_COST;
    }

    /**
     * Check if starting price can be updated
     */
    public function canUpdateStartingPrice(): bool
    {
        return \App\Services\Inventory\PriceService::canUpdateStartingPrice($this->id);
    }

    /**
     * Get impact of changing starting price
     */
    public function getStartingPriceChangeImpact(): array
    {
        return \App\Services\Inventory\PriceService::getStartingPriceChangeImpact($this->id);
    }

    // Code Generation Methods

    /**
     * Generate the next item code from settings
     */
    public static function generateNextItemCode(): string
    {
        $nextNumber = Setting::get('items', 'code_counter', 5000);
        return (string) $nextNumber;
    }

    // Relationship to get all warehouse inventories
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    // Get total inventory across all warehouses (using relationship if loaded, otherwise query)
    public function getTotalInventoryQuantityAttribute()
    {
        if ($this->relationLoaded('inventories')) {
            return $this->inventories->sum('quantity');
        }
        return $this->inventories()->sum('quantity');
    }

    // Get inventory quantity for a specific warehouse
    public function getWarehouseInventoryQuantity(int $warehouseId): int
    {
        return $this->inventories()
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0;
    }

    /**
     * Reserve the next code number (increment counter)
     */
    public static function reserveNextCode(): string
    {
        // Atomically get and increment the counter
        $newValue = Setting::incrementValue('items', 'code_counter');
        return (string) ($newValue - 1); // Return the value before increment
    }

    /**
     * Check if a code is unique
     */
    public static function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = self::withTrashed()->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * Get the next suggested code for frontend display
     */
    public static function getNextSuggestedCode(): string
    {
        return self::generateNextItemCode();
    }

    /**
     * Validate and set code for new item
     */
    public function setItemCode(?string $userCode = null): string
    {
        if ($userCode) {
            // User provided custom code - validate uniqueness
            if (!self::isCodeUnique($userCode)) {
                throw new \InvalidArgumentException("Code '{$userCode}' is already in use.");
            }
            $this->code = $userCode;

            // Only increment counter if user used the suggested code
            $suggestedCode = self::generateNextItemCode();
            if ($userCode === $suggestedCode) {
                Setting::incrementValue('items', 'code_counter');
            }
        } else {
            // Auto-generate code
            $this->code = self::reserveNextCode();
        }

        return $this->code;
    }

    /**
     * Get allowed document file extensions for this model (only images)
     */
    public function getAllowedDocumentExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    }

    /**
     * Get maximum file size for document uploads (in bytes)
     */
    public function getMaxDocumentFileSize(): int
    {
        return 5 * 1024 * 1024; // 5MB for images
    }

    /**
     * Get maximum number of documents allowed per item
     */
    public function getMaxDocumentsCount(): int
    {
        return 10; // 10 images maximum per item
    }

    /**
     * Handle code setting on model creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Auto-set code if not provided
            if (!$item->code) {
                $item->setItemCode();
            }
        });

        static::created(function ($item) {
            // Initialize price from starting_price after item is created
            if ($item->starting_price > 0) {
                \App\Services\Inventory\PriceService::initializeFromItem($item);
            }
        });

        static::updating(function ($item) {
            // Prevent starting_price changes if transactions exist
            if ($item->isDirty('starting_price')) {
                if (!$item->canUpdateStartingPrice()) {
                    $impact = $item->getStartingPriceChangeImpact();
                    throw new \InvalidArgumentException($impact['warning_message']);
                }
            }
        });
    }
}
