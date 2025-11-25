<?php

namespace App\Models\Suppliers;

use App\Helpers\AccountsHelper;
use App\Helpers\SuppliersHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Items\Item;
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

class Purchase extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'code',
        'date',
        'status',
        'prefix',
        'supplier_id',
        'warehouse_id',
        'currency_id',
        // 'account_id',
        'supplier_invoice_number',
        'currency_rate',
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
        'discount_amount',
        'discount_amount_usd',
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
        'discount_amount' => 'decimal:4',
        'discount_amount_usd' => 'decimal:4',
        'total' => 'decimal:4',
        'total_usd' => 'decimal:4',
        'final_total' => 'decimal:4',
        'final_total_usd' => 'decimal:4',
    ];

    protected $searchable = [
        'code',
        'prefix',
        'supplier_invoice_number',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date',
        'status',
        'supplier_id',
        'prefix',
        'warehouse_id',
        'currency_id',
        'supplier_invoice_number',
        'sub_total_usd',
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

    // public function account(): BelongsTo
    // {
    //     return $this->belongsTo(Account::class);
    // }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
    
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
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

    public function scopeBySupplierInvoiceNumber($query, $supplierInvoiceNumber)
    {
        return $query->where('supplier_invoice_number', $supplierInvoiceNumber);
    }

    // Accessors & Mutators
    public function getTotalItemsCountAttribute(): int
    {
        return $this->purchaseItems()->count();
    }

    public function getPurchaseCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->purchaseItems()->sum('quantity');
    }

    public function getHasItemsAttribute(): bool
    {
        return $this->purchaseItems()->exists();
    }



    // Business Logic Methods
    public function recalculateFromItems(): void
    {
        $items = $this->purchaseItems;
        
        $subTotal = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $total = $subTotal - $this->discount_amount;
        $totalUsd = $subTotalUsd - $this->discount_amount_usd;
        
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

    // Code generation methods
    public static function generateNextPurchaseCode(): string
    {
        $defaultValue = config('app.purchase_code_start', 1000);
        $nextNumber = Setting::getOrCreateCounter('purchases', 'code_counter', $defaultValue);
        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.purchase_code_start', 1000);
        $newValue = Setting::incrementValue('purchases', 'code_counter', 1, $defaultValue);
        return str_pad(($newValue - 1), 6, '0', STR_PAD_LEFT);
    }

    public static function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $query = static::where('code', $code);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    public static function getNextSuggestedCode(): string
    {
        return self::generateNextPurchaseCode();
    }

    public function setPurchaseCode(?string $userCode = null): string
    {
        if ($userCode) {
            if (!self::isCodeUnique($userCode)) {
                throw new \InvalidArgumentException("Code '{$userCode}' is already in use.");
            }
            $this->code = $userCode;
            
            $suggestedCode = self::generateNextPurchaseCode();
            if ($userCode === $suggestedCode) {
                $defaultValue = config('app.purchase_code_start', 1000);
                Setting::incrementValue('purchases', 'code_counter', 1, $defaultValue);
            }
        } else {
            $this->code = self::reserveNextCode();
        }
        
        return $this->code;
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

        static::creating(function ($purchase) {
            $purchase->status = 'Waiting';
            if (!$purchase->code) {
                $purchase->setPurchaseCode();
            }
        });

        static::created(function ($purchase) {
            // Add to supplier balance when purchase is created
            SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $purchase->final_total_usd);
        });

        static::updated(function ($purchase) {
            $original = $purchase->getOriginal();

            // Case 1: Supplier changed
            if ($original['supplier_id'] != $purchase->supplier_id) {
                SuppliersHelper::removeBalance(Supplier::find($original['supplier_id']), $original['final_total_usd']);
                SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $purchase->final_total_usd);
            }
            // Case 2: Amount changed on same supplier
            elseif ($original['final_total_usd'] != $purchase->final_total_usd) {
                $difference = $purchase->final_total_usd - $original['final_total_usd'];
                SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $difference);
            }
        });

        static::deleted(function ($purchase) {
            // Remove from supplier balance when purchase is deleted
            SuppliersHelper::removeBalance(Supplier::find($purchase->supplier_id), $purchase->final_total_usd);
        });

    }
}
