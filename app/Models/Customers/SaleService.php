<?php

namespace App\Models\Customers;

use App\Models\Services\Services;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use App\Traits\TracksActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleService extends Model
{
    /** @use HasFactory<\Database\Factories\Customers\SaleServiceFactory> */
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, TracksActivity;

    protected $fillable = [
        'sale_id',
        'service_id',
        'hsn',
        'date',
        'quantity',
        'unit_cost_price',
        'unit_price',
        'unit_price_usd',
        'unit_net_sell_price',
        'unit_net_sell_price_usd',
        'unit_ttc_price',
        'unit_ttc_price_usd',
        'discount_percent',
        'unit_discount_amount',
        'unit_discount_amount_usd',
        'total_discount_amount',
        'total_discount_amount_usd',
        'tax_percent',
        'tax_label',
        'unit_tax_amount',
        'unit_tax_amount_usd',
        'total_tax_amount',
        'total_tax_amount_usd',
        'total_net_sell_price',// without tax
        'total_net_sell_price_usd',
        'total_price', // with tax
        'total_price_usd',
        'unit_profit',
        'total_profit',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
        'unit_cost_price' => 'decimal:8',
        'unit_price' => 'decimal:8',
        'unit_price_usd' => 'decimal:8',
        'unit_net_sell_price' => 'decimal:8',
        'unit_net_sell_price_usd' => 'decimal:8',
        'unit_ttc_price' => 'decimal:8',
        'unit_ttc_price_usd' => 'decimal:8',
        'tax_percent' => 'decimal:8',
        'unit_tax_amount' => 'decimal:8',
        'unit_tax_amount_usd'=> 'decimal:8',
        'total_tax_amount' => 'decimal:8',
        'total_tax_amount_usd' => 'decimal:8',
        'discount_percent' => 'decimal:8',
        'unit_discount_amount' => 'decimal:8',
        'unit_discount_amount_usd' => 'decimal:8',
        'total_discount_amount' => 'decimal:8',
        'total_discount_amount_usd' => 'decimal:8',
        'total_net_sell_price' => 'decimal:8',
        'total_net_sell_price_usd' => 'decimal:8',
        'total_price' => 'decimal:8',
        'total_price_usd' => 'decimal:8',
        'unit_profit' => 'decimal:8',
        'total_profit' => 'decimal:8',
    ];

    protected $searchable = [
        'note',
    ];

    protected $sortable = [
        'id',
        'service_id',
        'date',
        'quantity',
        'unit_price',
        'total_price',
        'unit_profit',
        'total_profit',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Services, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    protected function getActivityLogParent()
    {
        // Ensure sale relationship is loaded (important for delete events)
        if (!$this->relationLoaded('sale')) {
            $this->load('sale');
        }
        return $this->sale;
    }

    protected function shouldSkipActivityLog(): bool
    {
        // Skip if parent sale doesn't exist or is being deleted
        $sale = $this->getActivityLogParent();
        if (!$sale || $sale->trashed()) {
            return true;
        }

        // Skip if parent sale is not approved
        return is_null($sale->approved_at);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($saleService) {
            self::applyTax($saleService);
        });

        static::updating(function ($saleService) {
            if ($saleService->isDirty(['service_id', 'tax_percent', 'unit_price', 'unit_price_usd', 'unit_discount_amount', 'unit_discount_amount_usd'])) {
                self::applyTax($saleService);
            }
        });
    }

    /**
     * Set tax label from the service's tax code and compute per-unit tax amount.
     */
    protected static function applyTax($saleService): void
    {
        if ($saleService->service_id) {
            $service = Services::with('taxCode')->find($saleService->service_id);
            $saleService->tax_label = ($saleService->tax_percent == 0 || !$service || !$service->taxCode)
                ? 'No'
                : $service->taxCode->name;

            // Snapshot HSN from the service (explicit value on the line wins)
            $saleService->hsn = $saleService->hsn ?: ($service->hsn ?? null);
        } else {
            $saleService->tax_label = $saleService->tax_percent == 0 ? 'No' : 'TVA';
        }

        $price = $saleService->unit_price - $saleService->unit_discount_amount;
        $priceUsd = $saleService->unit_price_usd - $saleService->unit_discount_amount_usd;
        $saleService->unit_tax_amount = $saleService->tax_percent > 0 ? $price * ($saleService->tax_percent / 100) : 0;
        $saleService->unit_tax_amount_usd = $saleService->tax_percent > 0 ? $priceUsd * ($saleService->tax_percent / 100) : 0;
    }
}
