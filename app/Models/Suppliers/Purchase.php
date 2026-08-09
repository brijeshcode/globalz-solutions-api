<?php

namespace App\Models\Suppliers;

use App\Contracts\ModuleLockable;
use App\Helpers\SuppliersHelper;
use App\Models\Suppliers\PurchaseExpense;
use Carbon\CarbonInterface;
use App\Models\Setting;
use App\Models\Setups\Supplier;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model implements ModuleLockable
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, HasDocuments, Searchable, Sortable, HasDateFilters;
    public const STATUS_WAITING = 'Waiting';
    public const DELIVERY_STATUS = ['Waiting', 'Shipped', 'Delivered'];
    public const TAXPREFIX = 'PUR';
    public const TAXFREEPREFIX = 'PAX';
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
        'sub_total',
        'sub_total_usd',
        'discount_amount',
        'discount_amount_usd',
        'total',
        'total_usd',
        'tax_usd',
        'tax_usd_percent',
        'total_expense_usd',
        'final_total',
        'final_total_usd',
        'note',
        'delivered_at',
    ];

    protected $casts = [
        'date'         => 'date',
        'delivered_at' => 'datetime',
        'currency_rate' => 'float',
        'sub_total' => 'float',
        'sub_total_usd' => 'float',
        'discount_amount' => 'float',
        'discount_amount_usd' => 'float',
        'total' => 'float',
        'total_usd' => 'float',
        'tax_usd' => 'float',
        'tax_usd_percent' => 'float',
        'total_expense_usd' => 'float',
        'final_total' => 'float',
        'final_total_usd' => 'float',
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
        'total_expense_usd',
        'final_total_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // public function account(): BelongsTo
    // {
    //     return $this->belongsTo(Account::class);
    // }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<PurchaseExpense, $this>
     */
    public function purchaseExpenses(): HasMany
    {
        return $this->hasMany(PurchaseExpense::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeBySupplier(Builder $query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByWarehouse(Builder $query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByCurrency(Builder $query, int $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDateRange(Builder $query, \DateTimeInterface|string $startDate, \DateTimeInterface|string $endDate)

    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByWaiting(Builder $query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeByCode(Builder $query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeBySupplierInvoiceNumber(Builder $query, string $supplierInvoiceNumber)
    {
        return $query->where('supplier_invoice_number', $supplierInvoiceNumber);
    }

    public function scopeByPrefix(Builder $query, string $prefix)
    {
        return $query->where('prefix', $prefix);
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

    // Code generation methods
    public static function generateNextPurchaseCode(): string
    {
        $defaultValue = config('app.purchase_code_start', 1000);
        $nextNumber = Setting::getOrCreateCounter('purchases', 'code_counter', $defaultValue);
        return str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.purchase_code_start', 1000);
        $newValue = Setting::incrementValue('purchases', 'code_counter', 1, $defaultValue);
        return str_pad((string) ($newValue - 1), 6, '0', STR_PAD_LEFT);
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
            if ($purchase->total_usd != 0) {
                SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $purchase->total_usd);
            }
        });

        static::updated(function ($purchase) {
            $original = $purchase->getOriginal();
            $originalTotalUsd = $original['total_usd'] ?? 0;

            // Case 1: Supplier changed - move balance from old to new supplier
            if ($original['supplier_id'] != $purchase->supplier_id) {
                SuppliersHelper::removeBalance(Supplier::find($original['supplier_id']), $originalTotalUsd);
                SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $purchase->total_usd);
            }
            // Case 2: Amount changed on same supplier
            elseif ($originalTotalUsd != $purchase->total_usd) {
                $difference = $purchase->total_usd - $originalTotalUsd;
                SuppliersHelper::addBalance(Supplier::find($purchase->supplier_id), $difference);
            }
        });

        static::deleted(function ($purchase) {
            SuppliersHelper::removeBalance(Supplier::find($purchase->supplier_id), $purchase->total_usd);
        });

    }

    // Module lock (see App\Contracts\ModuleLockable)
    public function moduleLockKey(): string
    {
        return 'purchase';
    }

    public function moduleLockDate(): ?CarbonInterface
    {
        return $this->date;
    }

    public function isModuleLockExempt(): bool
    {
        return $this->status !== 'Delivered';
    }
}
