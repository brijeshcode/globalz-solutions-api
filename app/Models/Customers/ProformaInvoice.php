<?php

namespace App\Models\Customers;

use App\Helpers\CommonHelper;
use App\Helpers\CurrencyHelper;
use App\Models\Employees\Employee;
use App\Models\Items\PriceList;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Services\Currency\CurrencyService;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use App\Traits\TracksActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoice extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, TracksActivity, HasDateFilters;

    public const TAXPREFIX     = 'PINV';
    public const TAXFREEPREFIX = 'PINX';

    public const STATUS_DRAFT     = 'Draft';
    public const STATUS_SENT      = 'Sent';
    public const STATUS_ACCEPTED  = 'Accepted';
    public const STATUS_REJECTED  = 'Rejected';
    public const STATUS_CONVERTED = 'Converted';

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
        'client_po_number',
        'currency_rate',
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
        'converted_at',
        'converted_sale_id',
    ];

    protected $casts = [
        'date'                 => 'datetime',
        'approved_at'          => 'datetime',
        'converted_at'         => 'datetime',
        'value_date'           => 'datetime',
        'currency_rate'        => 'decimal:4',
        'sub_total'            => 'decimal:8',
        'sub_total_usd'        => 'decimal:8',
        'discount_amount'      => 'decimal:8',
        'discount_amount_usd'  => 'decimal:8',
        'total'                => 'decimal:8',
        'total_usd'            => 'decimal:8',
        'total_profit'         => 'decimal:8',
        'total_volume_cbm'     => 'decimal:4',
        'total_weight_kg'      => 'decimal:4',
        'total_tax_amount'     => 'decimal:8',
        'total_tax_amount_usd' => 'decimal:8',
        'local_curreny_rate'   => 'decimal:8',
    ];

    protected $searchable = ['code', 'client_po_number', 'status', 'total_usd', 'prefix', 'note'];

    protected $sortable = [
        'id', 'code', 'status', 'date', 'prefix',
        'salesperson_id', 'customer_id', 'currency_id', 'warehouse_id',
        'client_po_number', 'total_usd', 'total_profit', 'created_at', 'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProformaInvoiceStatusHistory::class)->orderBy('created_at');
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
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

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function isConverted(): bool
    {
        return !is_null($this->converted_at);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isPending(): bool
    {
        return !$this->isConverted();
    }

    public function getProformaCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    public static function reserveNextCode(): string
    {
        $defaultValue = 1000;
        $newValue = Setting::incrementValue('proforma_invoices', 'code_counter', 1, $defaultValue);
        return str_pad(($newValue - 1), 6, '0', STR_PAD_LEFT);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeBySalesperson($query, $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeConverted($query)
    {
        return $query->whereNotNull('converted_at');
    }

    public function scopeNotConverted($query)
    {
        return $query->whereNull('converted_at');
    }

    // ─── Recalculation ───────────────────────────────────────────────────────

    public function recalculateTotalTax(): void
    {
        $totalTaxAmount    = $this->items->sum(fn($i) => $i->tax_amount * $i->quantity);
        $totalTaxAmountUsd = $this->items->sum(fn($i) => $i->tax_amount_usd * $i->quantity);

        $this->updateQuietly([
            'total_tax_amount'     => $totalTaxAmount,
            'total_tax_amount_usd' => $totalTaxAmountUsd,
        ]);
    }

    public function recalculateAllFields(): void
    {
        $currencyRate = $this->currency_rate ?? 1;
        $currencyId   = $this->currency_id;

        $totalProfit     = 0;
        $subTotal        = 0;
        $subTotalUsd     = 0;
        $saleTotalTax    = 0;
        $saleTotalTaxUsd = 0;
        $totalVolumeCbm  = 0;
        $totalWeightKg   = 0;

        $this->load('items.item.itemPrice');

        foreach ($this->items as $proformaItem) {
            $costPrice       = $proformaItem->item?->itemPrice?->price_usd ?? $proformaItem->cost_price ?? 0;
            $sellingPrice    = $proformaItem->price ?? 0;
            $quantity        = $proformaItem->quantity ?? 0;
            $discountPercent = $proformaItem->discount_percent ?? 0;
            $taxPercent      = $proformaItem->tax_percent ?? 0;

            $sellingPriceUsd          = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);
            $unitDiscountAmount        = $sellingPrice * ($discountPercent / 100);
            $unitDiscountAmountUsd     = $sellingPriceUsd * ($discountPercent / 100);
            $discountAmount            = $unitDiscountAmount * $quantity;
            $discountAmountUsd         = $unitDiscountAmountUsd * $quantity;
            $netSellPrice              = $sellingPrice - $unitDiscountAmount;
            $netSellPriceUsd           = $sellingPriceUsd - $unitDiscountAmountUsd;
            $taxAmount                 = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
            $taxAmountUsd              = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;
            $ttcPrice                  = $netSellPrice + $taxAmount;
            $ttcPriceUsd               = $netSellPriceUsd + $taxAmountUsd;
            $totalNetSellPrice         = $netSellPrice * $quantity;
            $totalNetSellPriceUsd      = $netSellPriceUsd * $quantity;
            $totalTaxAmount            = $taxAmount * $quantity;
            $totalTaxAmountUsd         = $taxAmountUsd * $quantity;
            $totalPrice                = $ttcPrice * $quantity;
            $totalPriceUsd             = $ttcPriceUsd * $quantity;
            $unitProfit                = $netSellPriceUsd - $costPrice;
            $itemTotalProfit           = $unitProfit * $quantity;

            $proformaItem->updateQuietly([
                'cost_price'               => $costPrice,
                'price_usd'                => $sellingPriceUsd,
                'unit_discount_amount'     => $unitDiscountAmount,
                'unit_discount_amount_usd' => $unitDiscountAmountUsd,
                'discount_amount'          => $discountAmount,
                'discount_amount_usd'      => $discountAmountUsd,
                'net_sell_price'           => $netSellPrice,
                'net_sell_price_usd'       => $netSellPriceUsd,
                'tax_amount'               => $taxAmount,
                'tax_amount_usd'           => $taxAmountUsd,
                'ttc_price'                => $ttcPrice,
                'ttc_price_usd'            => $ttcPriceUsd,
                'total_net_sell_price'     => $totalNetSellPrice,
                'total_net_sell_price_usd' => $totalNetSellPriceUsd,
                'total_tax_amount'         => $totalTaxAmount,
                'total_tax_amount_usd'     => $totalTaxAmountUsd,
                'total_price'              => $totalPrice,
                'total_price_usd'          => $totalPriceUsd,
                'unit_profit'              => $unitProfit,
                'total_profit'             => $itemTotalProfit,
            ]);

            $totalProfit     += $itemTotalProfit;
            $subTotal        += $totalNetSellPrice;
            $subTotalUsd     += $totalNetSellPriceUsd;
            $saleTotalTax    += $totalTaxAmount;
            $saleTotalTaxUsd += $totalTaxAmountUsd;
            $totalVolumeCbm  += $proformaItem->total_volume_cbm ?? 0;
            $totalWeightKg   += $proformaItem->total_weight_kg ?? 0;
        }

        $additionalDiscount    = $this->discount_amount ?? 0;
        $additionalDiscountUsd = $this->discount_amount_usd ?? 0;

        $this->updateQuietly([
            'sub_total'            => $subTotal,
            'sub_total_usd'        => $subTotalUsd,
            'total_tax_amount'     => $saleTotalTax,
            'total_tax_amount_usd' => $saleTotalTaxUsd,
            'total'                => $subTotal + $saleTotalTax - $additionalDiscount,
            'total_usd'            => $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd,
            'total_profit'         => $totalProfit - $additionalDiscountUsd,
            'total_volume_cbm'     => $totalVolumeCbm,
            'total_weight_kg'      => $totalWeightKg,
        ]);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    protected function getActivityLogAttributes(): array
    {
        return [
            'date', 'currency_id', 'warehouse_id', 'total', 'total_usd',
            'sub_total', 'sub_total_usd', 'discount_amount', 'discount_amount_usd',
            'total_profit', 'note', 'value_date', 'status',
        ];
    }

    protected function shouldSkipActivityLog(): bool
    {
        return false;
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($proforma) {
            if (!$proforma->code) {
                $proforma->code = self::reserveNextCode();
            }
            $proforma->status = $proforma->status ?? self::STATUS_DRAFT;

            $customer = Customer::select('price_list_id_INV', 'price_list_id_INX')->find($proforma->customer_id);
            if ($customer) {
                $proforma->price_list_id = $proforma->prefix === self::TAXFREEPREFIX
                    ? $customer->price_list_id_INX
                    : $customer->price_list_id_INV;
            }

            if ($proforma->customer_payment_term_id) {
                $paymentTerm = CustomerPaymentTerm::find($proforma->customer_payment_term_id);
                if ($paymentTerm && $paymentTerm->days) {
                    $proforma->value_date = $proforma->date->addDays($paymentTerm->days);
                }
            }

            $localCurrency = Currency::with('activeRate')
                ->where('code', CurrencyService::getLocalCurrencyCode())
                ->first();
            $proforma->local_curreny_rate = $localCurrency && $localCurrency->activeRate
                ? $localCurrency->activeRate->rate
                : 1;
            $proforma->invoice_tax_label = CommonHelper::getTaxLable();
            $proforma->invoice_nb1       = CommonHelper::invoiceNb1();
            $proforma->invoice_nb2       = CommonHelper::invoiceNb2();
        });

        static::created(function ($proforma) {
            $proforma->statusHistories()->create([
                'status'     => $proforma->status,
                'changed_by' => $proforma->created_by,
            ]);
        });

        static::updating(function ($proforma) {
            if ($proforma->isDirty('customer_payment_term_id') || $proforma->isDirty('date')) {
                if ($proforma->customer_payment_term_id) {
                    $paymentTerm = CustomerPaymentTerm::find($proforma->customer_payment_term_id);
                    if ($paymentTerm && $paymentTerm->days) {
                        $proforma->value_date = $proforma->date->addDays($paymentTerm->days);
                    }
                }
            }
        });
    }
}
