<?php

namespace App\Models\Suppliers;

use App\Helpers\SuppliersHelper;
use App\Models\Setting;
use App\Models\Setups\Supplier;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateWithTime;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, HasDocuments, Searchable, Sortable;
    public const STATUS_WAITING = 'Waiting';

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'shipping_status',
        'supplier_id',
        'warehouse_id',
        'currency_id',
        'currency_rate',
        'supplier_purchase_return_number',
        'shipping_fee_usd',
        'customs_fee_usd',
        'other_fee_usd',
        'tax_usd',
        'shipping_fee_usd_percent',
        'customs_fee_usd_percent',
        'other_fee_usd_percent',
        'tax_usd_percent',
        'sub_total',
        'sub_total_usd',
        'additional_charge_amount',
        'additional_charge_amount_usd',
        'total',
        'total_usd',
        'final_total',
        'final_total_usd',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'currency_rate' => 'decimal:6',
        'shipping_fee_usd' => 'decimal:4',
        'customs_fee_usd' => 'decimal:4',
        'other_fee_usd' => 'decimal:4',
        'tax_usd' => 'decimal:4',
        'shipping_fee_usd_percent' => 'decimal:2',
        'customs_fee_usd_percent' => 'decimal:2',
        'other_fee_usd_percent' => 'decimal:2',
        'tax_usd_percent' => 'decimal:2',
        'sub_total' => 'decimal:4',
        'sub_total_usd' => 'decimal:4',
        'additional_charge_amount' => 'decimal:4',
        'additional_charge_amount_usd' => 'decimal:4',
        'total' => 'decimal:4',
        'total_usd' => 'decimal:4',
        'final_total' => 'decimal:4',
        'final_total_usd' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'prefix',
        'supplier_purchase_return_number',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'prefix',
        'shipping_status',
        'supplier_id',
        'warehouse_id',
        'currency_id',
        'supplier_purchase_return_number',
        'sub_total_usd',
        'total_usd',
        'final_total_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }


    // Scopes
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByWaiting($query)
    {
        return $query->where('shipping_status', self::STATUS_WAITING);
    }

    public function scopeBySupplierPurchaseReturnNumber($query, $supplierPurchaseReturnNumber)
    {
        return $query->where('supplier_purchase_return_number', $supplierPurchaseReturnNumber);
    }

    public function scopeByShippingStatus($query, $shippingStatus)
    {
        return $query->where('shipping_status', $shippingStatus);
    }

    // Accessors & Mutators
    public function getTotalItemsCountAttribute(): int
    {
        return $this->purchaseReturnItems()->count();
    }

    public function getPurchaseReturnCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->purchaseReturnItems()->sum('quantity');
    }

    public function getHasItemsAttribute(): bool
    {
        return $this->purchaseReturnItems()->exists();
    }

    // Business Logic Methods
    public function recalculateFromItems(): void
    {
        $items = $this->purchaseReturnItems;

        $subTotal = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $total = $subTotal + $this->additional_charge_amount;
        $totalUsd = $subTotalUsd + $this->additional_charge_amount_usd;

        $finalTotal = $total;
        $finalTotalUsd = $totalUsd + $this->shipping_fee_usd + $this->customs_fee_usd
                        + $this->other_fee_usd + $this->tax_usd;

        $this->update([
            'sub_total' => $subTotal,
            'sub_total_usd' => $subTotalUsd,
            'total' => $total,
            'total_usd' => $totalUsd,
            'final_total' => $finalTotal,
            'final_total_usd' => $finalTotalUsd,
        ]);
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $settingKey = 'purchase_returns';
        $defaultValue = config('app.purchase_return_code_start', 1000);
        $newValue = Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setPurchaseReturnCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Document Methods
    public function getAllowedDocumentExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'txt'];
    }

    public function getMaxDocumentFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB
    }

    public function getMaxDocumentsCount(): int
    {
        return 15;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchaseReturn) {
            if (!$purchaseReturn->code) {
                $purchaseReturn->setPurchaseReturnCode();
            }
            $purchaseReturn->prefix = 'PURTN';
        });

        static::created(function ($purchaseReturn) {
            // Purchase return reduces supplier balance (we returned goods to them)
            SuppliersHelper::removeBalance(Supplier::find($purchaseReturn->supplier_id), $purchaseReturn->final_total_usd);
        });

        static::updated(function ($purchaseReturn) {
            $original = $purchaseReturn->getOriginal();

            // Case 1: Supplier changed
            if ($original['supplier_id'] != $purchaseReturn->supplier_id) {
                SuppliersHelper::addBalance(Supplier::find($original['supplier_id']), $original['final_total_usd']);
                SuppliersHelper::removeBalance(Supplier::find($purchaseReturn->supplier_id), $purchaseReturn->final_total_usd);
            }
            // Case 2: Amount changed on same supplier
            elseif ($original['final_total_usd'] != $purchaseReturn->final_total_usd) {
                $difference = $purchaseReturn->final_total_usd - $original['final_total_usd'];
                SuppliersHelper::removeBalance(Supplier::find($purchaseReturn->supplier_id), $difference);
            }
        });

        static::deleted(function ($purchaseReturn) {
            // Restore supplier balance when purchase return is deleted
            SuppliersHelper::addBalance(Supplier::find($purchaseReturn->supplier_id), $purchaseReturn->final_total_usd);
        });
    }
}
