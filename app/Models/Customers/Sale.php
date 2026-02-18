<?php

namespace App\Models\Customers;

use App\Helpers\CommonHelper;
use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Services\Currency\CurrencyService;
use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Models\Items\PriceList;
use App\Traits\HasDateFilters;
use App\Traits\Authorable;
use App\Traits\TracksActivity;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Sale extends Model
{
    public const TAXSALEPREFIX = 'INV';
    public const NOTAXSALEPREFIX = 'INX';

    public const TAXPREFIX = 'INV';
    public const TAXFREEPREFIX = 'INX';

    public const STATUS_WAITING = 'Waiting';

    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, TracksActivity, HasDateFilters;

    protected $fillable = [
        'code',
        'date',
        'prefix',
        'status',
        'salesperson_id',
        'customer_id',
        'currency_id',
        'warehouse_id',
        'price_list_id',
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
        'total_volume_cbm',
        'total_weight_kg',
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
        'sub_total' => 'decimal:8',
        'sub_total_usd' => 'decimal:8',
        'discount_amount' => 'decimal:8',
        'discount_amount_usd' => 'decimal:8',
        'total' => 'decimal:8',
        'total_usd' => 'decimal:8',
        'total_profit' => 'decimal:8',
        'total_volume_cbm' => 'decimal:4',
        'total_weight_kg' => 'decimal:4',
        'total_tax_amount' => 'decimal:8',
        'total_tax_amount_usd' => 'decimal:8',
        'local_curreny_rate' => 'decimal:8',
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

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // activity tracking
    protected function getActivityLogAttributes(): array
    {
        return [
            'date',
            'currency_id',
            'warehouse_id',
            'total',
            'total_usd',
            'sub_total',
            'sub_total_usd',
            'discount_amount',
            'discount_amount_usd',
            'total_profit',
            'total_volume_cbm',
            'total_weight_kg',
            'note',
            'value_date',
            'total_tax_amount',
            'total_tax_amount_usd',
            'local_curreny_rate',
            'invoice_tax_label',
        ];
    }

    // we will not log un-approved sales 
    protected function shouldSkipActivityLog(): bool
    {
        return is_null($this->approved_at);
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

    public function scopeByWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
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

    /**
     * Recalculate all sale items and sale totals based on the new calculation formula
     * This method recalculates all derived fields from base inputs: price, quantity, discount_percent, tax_percent
     */
    public function recalculateAllFields(): void
    {
        $currencyRate = $this->currency_rate ?? 1;
        $currencyId = $this->currency_id;

        $totalProfit = 0;
        $subTotal = 0;
        $subTotalUsd = 0;
        $saleTotalTax = 0;
        $saleTotalTaxUsd = 0;
        $totalVolumeCbm = 0;
        $totalWeightKg = 0;

        // Reload sale items to ensure fresh data
        $this->load('saleItems.item.itemPrice');

        foreach ($this->saleItems as $saleItem) {
            // Get cost price from item
            $costPrice = $saleItem->item?->itemPrice?->price_usd ?? $saleItem->cost_price ?? 0;

            // Base values from sale item
            $sellingPrice = $saleItem->price ?? 0;
            $quantity = $saleItem->quantity ?? 0;
            $discountPercent = $saleItem->discount_percent ?? 0;
            $taxPercent = $saleItem->tax_percent ?? 0;

            // Convert base price to USD
            $sellingPriceUsd = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);

            // Step 1: Calculate unit discount amount from discount percent
            $unitDiscountAmount = $sellingPrice * ($discountPercent / 100);
            $unitDiscountAmountUsd = $sellingPriceUsd * ($discountPercent / 100);

            // Step 2: Calculate total discount amount
            $discountAmount = $unitDiscountAmount * $quantity;
            $discountAmountUsd = $unitDiscountAmountUsd * $quantity;

            // Step 3: Calculate net sell price
            $netSellPrice = $sellingPrice - $unitDiscountAmount;
            $netSellPriceUsd = $sellingPriceUsd - $unitDiscountAmountUsd;

            // Step 4: Calculate tax amount based on net sell price
            $taxAmount = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
            $taxAmountUsd = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;

            // Step 5: Calculate TTC price
            $ttcPrice = $netSellPrice + $taxAmount;
            $ttcPriceUsd = $netSellPriceUsd + $taxAmountUsd;

            // Step 6: Calculate total net sell prices
            $totalNetSellPrice = $netSellPrice * $quantity;
            $totalNetSellPriceUsd = $netSellPriceUsd * $quantity;

            // Step 7: Calculate total tax amounts
            $totalTaxAmount = $taxAmount * $quantity;
            $totalTaxAmountUsd = $taxAmountUsd * $quantity;

            // Step 8: Calculate total price
            $totalPrice = $ttcPrice * $quantity;
            $totalPriceUsd = $ttcPriceUsd * $quantity;

            // Step 9: Calculate profit
            $unitProfit = $netSellPriceUsd - $costPrice;
            $itemTotalProfit = $unitProfit * $quantity;

            // Update sale item without firing events
            $saleItem->updateQuietly([
                'cost_price' => $costPrice,
                'price_usd' => $sellingPriceUsd,
                'unit_discount_amount' => $unitDiscountAmount,
                'unit_discount_amount_usd' => $unitDiscountAmountUsd,
                'discount_amount' => $discountAmount,
                'discount_amount_usd' => $discountAmountUsd,
                'net_sell_price' => $netSellPrice,
                'net_sell_price_usd' => $netSellPriceUsd,
                'tax_amount' => $taxAmount,
                'tax_amount_usd' => $taxAmountUsd,
                'ttc_price' => $ttcPrice,
                'ttc_price_usd' => $ttcPriceUsd,
                'total_net_sell_price' => $totalNetSellPrice,
                'total_net_sell_price_usd' => $totalNetSellPriceUsd,
                'total_tax_amount' => $totalTaxAmount,
                'total_tax_amount_usd' => $totalTaxAmountUsd,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPriceUsd,
                'unit_profit' => $unitProfit,
                'total_profit' => $itemTotalProfit,
            ]);

            // Aggregate totals for the sale
            $totalProfit += $itemTotalProfit;
            $subTotal += $totalNetSellPrice;
            $subTotalUsd += $totalNetSellPriceUsd;
            $saleTotalTax += $totalTaxAmount;
            $saleTotalTaxUsd += $totalTaxAmountUsd;
            $totalVolumeCbm += $saleItem->total_volume_cbm ?? 0;
            $totalWeightKg += $saleItem->total_weight_kg ?? 0;
        }

        // Sale-level discount
        $additionalDiscount = $this->discount_amount ?? 0;
        $additionalDiscountUsd = $this->discount_amount_usd ?? 0;

        // Update sale totals without firing events
        $this->updateQuietly([
            'sub_total' => $subTotal,
            'sub_total_usd' => $subTotalUsd,
            'total_tax_amount' => $saleTotalTax,
            'total_tax_amount_usd' => $saleTotalTaxUsd,
            'total' => $subTotal + $saleTotalTax - $additionalDiscount,
            'total_usd' => $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd,
            'total_profit' => $totalProfit - $additionalDiscountUsd,
            'total_volume_cbm' => $totalVolumeCbm,
            'total_weight_kg' => $totalWeightKg,
        ]);
    }

    /**
     * Recalculate all sales in the database
     * Use with caution - this will update all sales
     *
     * @param int|null $limit Limit the number of sales to process
     * @return array Statistics about the recalculation
     */
    public static function recalculateAllSales(?int $limit = null): array
    {
        $query = self::with(['saleItems.item.itemPrice']);

        if ($limit) {
            $query->limit($limit);
        }

        $sales = $query->get();
        $total = $sales->count();
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($sales as $sale) {
            try {
                $sale->recalculateAllFields();
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Sale #{$sale->id}: " . $e->getMessage();
            }
        }

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
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
            $customer = Customer::select('price_list_id_INV', 'price_list_id_INX')->find($sale->customer_id);
            if( !is_null($customer) ){
                $sale->price_list_id = $sale->prefix ==  self::TAXFREEPREFIX ? $customer->price_list_id_INX : $customer->price_list_id_INV;
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
            $sale->invoice_tax_label = CommonHelper::getTaxLable();
            $sale->invoice_nb1 = CommonHelper::invoiceNb1();
            $sale->invoice_nb2 = CommonHelper::invoiceNb2();
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
            // Use withTrashed() to include already soft-deleted items
            $saleItems = \App\Models\Customers\SaleItems::withTrashed()->where('sale_id', $sale->id)->get();

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
