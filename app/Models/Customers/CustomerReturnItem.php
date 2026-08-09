<?php

namespace App\Models\Customers;

use App\Models\Items\Item;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use App\Traits\TracksActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturnItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, TracksActivity;

    protected $fillable = [
        'item_code',
        'customer_return_id',
        'item_id',
        'sale_id',
        'sale_item_id',
        'quantity',
        'price',
        'price_usd',
        'discount_percent',
        'unit_discount_amount',
        'unit_discount_amount_usd',
        'discount_amount',
        'discount_amount_usd',
        'tax_percent',
        'tax_label',
        'tax_amount',
        'tax_amount_usd',
        'ttc_price',
        'ttc_price_usd',
        'unit_taxable_amount',
        'unit_taxable_amount_usd',
        'total_taxable_amount',
        'total_taxable_amount_usd',
        'total_tax_amount', 
        'total_tax_amount_usd',
        'total_price',
        'total_price_usd',
        'total_profit',
        'total_volume_cbm',
        'total_weight_kg',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'unit_discount_amount' => 'decimal:2',
        'unit_discount_amount_usd' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_amount_usd' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_amount_usd' => 'decimal:2',
        'ttc_price' => 'decimal:2',
        'ttc_price_usd' => 'decimal:2',
        'unit_taxable_amount' => 'decimal:2',
        'unit_taxable_amount_usd' => 'decimal:2',
        'total_taxable_amount' => 'decimal:2',
        'total_taxable_amount_usd' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_tax_amount_usd' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_price_usd' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'total_volume_cbm' => 'decimal:4',
        'total_weight_kg' => 'decimal:4',
    ];

    protected $searchable = [
        'item_code',
        'note',
    ];

    protected $sortable = [
        'id',
        'item_code',
        'quantity',
        'price',
        'total_price',
        'total_price_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    /**
     * @return BelongsTo<CustomerReturn, $this>
     */
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * @return BelongsTo<SaleItems, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItems::class, 'sale_item_id');
    }

    // Activity tracking 

     protected function getActivityLogAttributes(): array
    {
        return [
            'item_id',
            'sale_id',
            'sale_item_id',
            'quantity',
            'price',
            'price_usd',
            'discount_percent',
            'unit_discount_amount',
            'unit_discount_amount_usd',
            'discount_amount',
            'discount_amount_usd',
            'tax_percent',
            'tax_label',
            'tax_amount',
            'tax_amount_usd',
            'ttc_price',
            'ttc_price_usd',
            'unit_taxable_amount',
            'unit_taxable_amount_usd',
            'total_taxable_amount',
            'total_taxable_amount_usd',
            'total_tax_amount',
            'total_tax_amount_usd',
            'total_price',
            'total_price_usd',
            'total_profit',
            'note',
        ];
    }

    protected function getActivityLogParent()
    {
        return $this->customerReturn; // Relationship to parent
    }

    // Scopes
    public function scopeByCustomerReturn(Builder $query, int $customerReturnId)
    {
        return $query->where('customer_return_id', $customerReturnId);
    }

    public function scopeByItem(Builder $query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByItemCode(Builder $query, string $itemCode)
    {
        return $query->where('item_code', $itemCode);
    }

    public function scopeBySale(Builder $query, int $saleId)
    {
        return $query->where('sale_id', $saleId);
    }

    // Helper Methods
    public function calculateDiscountAmount(): float
    {
        if ($this->discount_percent > 0) {
            return ($this->price * $this->quantity * $this->discount_percent) / 100;
        }

        return $this->unit_discount_amount * $this->quantity;
    }

    public function calculateTotalPrice(): float
    {
        $subtotal = $this->price * $this->quantity;
        return $subtotal - $this->calculateDiscountAmount();
    }

    public function calculateTotalPriceWithTax(): float
    {
        $totalPrice = $this->calculateTotalPrice();
        $taxAmount = ($totalPrice * $this->tax_percent) / 100;
        return $totalPrice + $taxAmount;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // $item->discount_amount = $item->calculateDiscountAmount();
            // $item->total_price = $item->calculateTotalPrice();
            // $item->ttc_price = $item->calculateTotalPriceWithTax();
        });
    }
}
