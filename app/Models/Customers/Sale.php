<?php

namespace App\Models\Customers;

use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Services\Customers\CustomerBalanceService;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    public const TAXSALEPREFIX = 'INV';
    public const NOTAXSALEPREFIX = 'INX';

    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'status',
        'salesperson_id',
        'customer_id',
        'currency_id',
        'warehouse_id',
        'customer_payment_term_id',
        'customer_last_payment_receipt_id',
        'client_po_number',
        'currency_rate',
        'credit_limit',
        'outStanding_balance',
        'sub_total',
        'sub_total_usd',
        'discount_amount',
        'discount_amount_usd',
        'total',
        'total_usd',
        'total_profit',
        'approved_by',
        'approved_at',
        'approve_note',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'approved_at' => 'datetime',
        'currency_rate' => 'decimal:4',
        'credit_limit' => 'decimal:2',
        'outStanding_balance' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'sub_total_usd' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_amount_usd' => 'decimal:2',
        'total' => 'decimal:2',
        'total_usd' => 'decimal:2',
        'total_profit' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'client_po_number',
        'status',
        'total_usd',
        'prefix',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'status',
        'date',
        'prefix',
        'salesperson_id',
        'customer_id',
        'currency_id',
        'warehouse_id',
        'client_po_number',
        // 'sub_total',
        // 'total',
        'total_usd',
        'total_profit',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItems::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItems::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }


    // local scopes 

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopePending($query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySalesperson($query, $salepersonId)
    {
        return $query->where('salesperson_id', $salepersonId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }


    public function getSaleCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public function getInvoicePrefixsAttribure(): array
    {
        return [self::TAXSALEPREFIX, self::NOTAXSALEPREFIX];
    }

    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.sale_code_start', 1000);
        $newValue = Setting::incrementValue('sales', 'code_counter', 1, $defaultValue);
        return str_pad(($newValue - 1), 6, '0', STR_PAD_LEFT);
    }
    
    public function setSaleCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Helper Methods
    public function isApproved(): bool
    {
        return !is_null($this->approved_by);
    }

    public function isPending(): bool
    {
        return is_null($this->approved_by);
    }
    
    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            $sale->status = 'Waiting';
            if (!$sale->code) {
                $sale->setSaleCode();
            }
        });
        static::created(function ($sale) {
            // Only update customer balance if sale is approved
            if ($sale->isApproved()) {
                CustomerBalanceService::updateMonthlyTotal($sale->customer_id, 'sale', $sale->total_usd, $sale->id);
            }
        });


        static::deleted(function ($sale) {
            // When sale is deleted, restore inventory by manually processing each sale item
            // since the relationship might not work correctly after soft delete
            $saleItems = \App\Models\Customers\SaleItems::where('sale_id', $sale->id)->get();

            foreach ($saleItems as $saleItem) {
                // Restore inventory
                \App\Services\Inventory\InventoryService::add(
                    $saleItem->item_id,
                    $sale->warehouse_id,
                    $saleItem->quantity,
                    "Sale #{$sale->code} - Sale deleted/cancelled"
                );

                // Delete the sale item
                $saleItem->delete();
            }
        });
    }
}
