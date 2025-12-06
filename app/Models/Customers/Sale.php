<?php

namespace App\Models\Customers;

use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Services\Currency\CurrencyService;
use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Traits\Authorable;
use App\Traits\HasDateWithTime;
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

    public const TAXPREFIX = 'INV';
    public const TAXFREEPREFIX = 'INX';

    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable;

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
        'value_date',
        'total_tax_amount',
        'total_tax_amount_usd',
        'local_curreny_rate',
        'invoice_tax_label',
        'invoice_nb1',
        'invoice_nb2',
    ];

    protected $casts = [
        'date' => 'datetime',
        'approved_at' => 'datetime',
        'value_date' => 'datetime',
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
        'total_tax_amount' => 'decimal:2',
        'total_tax_amount_usd' => 'decimal:2',
        'local_curreny_rate' => 'decimal:4',
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

    /**
     * Setter for total_tax_amount_usd attribute.
     * Automatically converts total_tax_amount to USD based on currency_id.
     * If currency is already USD, uses the same value as total_tax_amount.
     */
    public function setTotalTaxAmountUsdAttribute($value): void
    {
        // Check if currency_id is set
        if (!$this->currency_id) {
            $this->attributes['total_tax_amount_usd'] = $value;
            return;
        }

        // Check if total_tax_amount is set
        if (!isset($this->attributes['total_tax_amount'])) {
            $this->attributes['total_tax_amount_usd'] = $value;
            return;
        }

        // If currency is USD, use the same value as total_tax_amount
        if (CurrencyService::isUSD($this->currency_id)) {
            $this->attributes['total_tax_amount_usd'] = $this->attributes['total_tax_amount'];
        } else {
            // Convert total_tax_amount to USD
            $currencyRate = $this->currency_rate ?? null;
            $this->attributes['total_tax_amount_usd'] = CurrencyHelper::toUsd(
                $this->currency_id,
                (float) $this->attributes['total_tax_amount'],
                $currencyRate
            );
        }
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

    public function recalculateTotalTax(): void
    {
        $totalTaxAmount = $this->saleItems->sum(function ($item) {
            return $item->tax_amount * $item->quantity;
        });

        $totalTaxAmountUsd = $this->saleItems->sum(function ($item) {
            return $item->tax_amount_usd * $item->quantity;
        });

        $this->updateQuietly([
            'total_tax_amount' => $totalTaxAmount,
            'total_tax_amount_usd' => $totalTaxAmountUsd,
        ]);
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

            // Calculate value_date based on payment term
            if ($sale->customer_payment_term_id) {
                $paymentTerm = CustomerPaymentTerm::find($sale->customer_payment_term_id);
                if ($paymentTerm && $paymentTerm->days) {
                    $sale->value_date = $sale->date->addDays($paymentTerm->days);
                }
            }

            $localCurrency = Currency::with('activeRate')->where('code', config('app.local_currency'))->first();
            $sale->local_curreny_rate = $localCurrency && $localCurrency->activeRate ? $localCurrency->activeRate->rate : 1;
            $sale->invoice_tax_label = 'TVA 11%';
            $sale->invoice_nb1 = 'Payment in USD or Market Price.';
            $sale->invoice_nb2 = 'ملحظة : ألضريبة على ألقيمة المضافة ل تسترد بعد ثلثة أشهر من تاريخ إصدار ألفاتورة';
        });

        static::created(function ($sale) {
            // Only update customer balance if sale is approved
            if ($sale->isApproved()) {
                CustomersHelper::removeBalance(Customer::find($sale->customer_id), $sale->total_usd);
            }
        });

        static::updating(function ($sale) {
            // Recalculate value_date only if payment term or date changes
            if ($sale->isDirty('customer_payment_term_id') || $sale->isDirty('date')) {
                if ($sale->customer_payment_term_id) {
                    $paymentTerm = CustomerPaymentTerm::find($sale->customer_payment_term_id);
                    if ($paymentTerm && $paymentTerm->days) {
                        $sale->value_date = $sale->date->addDays($paymentTerm->days);
                    }
                }
            }
        });

        static::updated(function ($sale) {
            // Only update balance for approved sales
            if (!$sale->isApproved()) {
                return;
            }

            $original = $sale->getOriginal();

            // Case 1: Customer changed
            if ($original['customer_id'] != $sale->customer_id) {
                CustomersHelper::addBalance(Customer::find($original['customer_id']), $original['total_usd']);
                CustomersHelper::removeBalance(Customer::find($sale->customer_id), $sale->total_usd);
            }
            // Case 2: Total amount changed
            elseif ($original['total_usd'] != $sale->total_usd) {
                $difference = $sale->total_usd - $original['total_usd'];
                CustomersHelper::removeBalance(Customer::find($sale->customer_id), $difference);
            }
        });

        static::deleted(function ($sale) {
            // Restore customer balance if sale was approved
            if ($sale->isApproved()) {
                CustomersHelper::addBalance(Customer::find($sale->customer_id), $sale->total_usd);
            }

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
