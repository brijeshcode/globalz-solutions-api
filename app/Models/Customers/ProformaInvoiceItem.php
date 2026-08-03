<?php

namespace App\Models\Customers;

use App\Models\Items\Item;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use App\Traits\TracksActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoiceItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, TracksActivity;

    protected $fillable = [
        'item_code',
        'proforma_invoice_id',
        'item_id',
        'quantity',
        'cost_price',
        'price',
        'price_usd',
        'net_sell_price',
        'net_sell_price_usd',
        'ttc_price',
        'ttc_price_usd',
        'discount_percent',
        'unit_discount_amount',
        'unit_discount_amount_usd',
        'discount_amount',
        'discount_amount_usd',
        'tax_percent',
        'tax_label',
        'tax_amount',
        'tax_amount_usd',
        'total_tax_amount',
        'total_tax_amount_usd',
        'total_net_sell_price',
        'total_net_sell_price_usd',
        'total_price',
        'total_price_usd',
        'unit_profit',
        'total_profit',
        'unit_volume_cbm',
        'unit_weight_kg',
        'total_volume_cbm',
        'total_weight_kg',
        'note',
    ];

    protected $casts = [
        'quantity'                  => 'decimal:0',
        'cost_price'                => 'decimal:8',
        'price'                     => 'decimal:8',
        'price_usd'                 => 'decimal:8',
        'net_sell_price'            => 'decimal:8',
        'net_sell_price_usd'        => 'decimal:8',
        'ttc_price'                 => 'decimal:8',
        'ttc_price_usd'             => 'decimal:8',
        'tax_percent'               => 'decimal:8',
        'tax_amount'                => 'decimal:8',
        'tax_amount_usd'            => 'decimal:8',
        'total_tax_amount'          => 'decimal:8',
        'total_tax_amount_usd'      => 'decimal:8',
        'discount_percent'          => 'decimal:8',
        'unit_discount_amount'      => 'decimal:8',
        'unit_discount_amount_usd'  => 'decimal:8',
        'discount_amount'           => 'decimal:8',
        'discount_amount_usd'       => 'decimal:8',
        'total_net_sell_price'      => 'decimal:8',
        'total_net_sell_price_usd'  => 'decimal:8',
        'total_price'               => 'decimal:8',
        'total_price_usd'           => 'decimal:8',
        'unit_profit'               => 'decimal:8',
        'total_profit'              => 'decimal:8',
        'unit_volume_cbm'           => 'decimal:4',
        'unit_weight_kg'            => 'decimal:4',
        'total_volume_cbm'          => 'decimal:3',
        'total_weight_kg'           => 'decimal:3',
    ];

    protected $searchable = ['item_code', 'note'];

    protected $sortable = [
        'id', 'item_code', 'item_id', 'quantity',
        'price', 'total_price', 'unit_profit', 'total_profit',
        'created_at', 'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    protected function getActivityLogParent()
    {
        if (!$this->relationLoaded('proformaInvoice')) {
            $this->load('proformaInvoice');
        }
        return $this->proformaInvoice;
    }

    protected function shouldSkipActivityLog(): bool
    {
        $proforma = $this->getActivityLogParent();
        return !$proforma || $proforma->trashed();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if ($item->item_id) {
                $model = Item::with('taxCode')->find($item->item_id);
                if ($model) {
                    $item->tax_label = ($item->tax_percent == 0 || !$model->taxCode)
                        ? 'No'
                        : $model->taxCode->name;
                    $item->unit_volume_cbm  = $model->volume ?? 0;
                    $item->unit_weight_kg   = $model->weight ?? 0;
                    $item->total_volume_cbm = ($model->volume ?? 0) * $item->quantity;
                    $item->total_weight_kg  = ($model->weight ?? 0) * $item->quantity;
                }
            } else {
                $item->tax_label = $item->tax_percent == 0 ? 'No' : 'TVA';
            }

            $price    = $item->price - $item->unit_discount_amount;
            $priceUsd = $item->price_usd - $item->unit_discount_amount_usd;
            $item->tax_amount     = $item->tax_percent > 0 ? $price * ($item->tax_percent / 100) : 0;
            $item->tax_amount_usd = $item->tax_percent > 0 ? $priceUsd * ($item->tax_percent / 100) : 0;
        });

        static::updating(function ($item) {
            if ($item->isDirty(['item_id', 'quantity', 'tax_percent'])) {
                if ($item->item_id) {
                    $model = Item::with('taxCode')->find($item->item_id);
                    if ($model) {
                        if ($item->isDirty(['item_id', 'tax_percent'])) {
                            $item->tax_label = ($item->tax_percent == 0 || !$model->taxCode)
                                ? 'No'
                                : $model->taxCode->name;
                        }
                        if ($item->isDirty(['item_id', 'quantity'])) {
                            $item->unit_volume_cbm  = $model->volume ?? 0;
                            $item->unit_weight_kg   = $model->weight ?? 0;
                            $item->total_volume_cbm = ($model->volume ?? 0) * $item->quantity;
                            $item->total_weight_kg  = ($model->weight ?? 0) * $item->quantity;
                        }
                    }
                } else {
                    $item->tax_label = $item->tax_percent == 0 ? 'No' : 'TVA';
                }
            }

            $price    = $item->price - $item->unit_discount_amount;
            $priceUsd = $item->price_usd - $item->unit_discount_amount_usd;
            $item->tax_amount     = $item->tax_percent > 0 ? $price * ($item->tax_percent / 100) : 0;
            $item->tax_amount_usd = $item->tax_percent > 0 ? $priceUsd * ($item->tax_percent / 100) : 0;
        });
    }
}
