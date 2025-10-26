<?php

namespace App\Models\Customers;

use App\Models\Items\Item;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturnItem extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'item_code',
        'customer_return_id',
        'item_id',
        'quantity',
        'price',
        'discount_percent',
        'unit_discount_amount',
        'discount_amount',
        'tax_percent',
        'ttc_price',
        'total_price',
        'total_price_usd',
        'total_volume_cbm',
        'total_weight_kg',
        'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:0',
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'unit_discount_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'ttc_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_price_usd' => 'decimal:2',
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
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Scopes
    public function scopeByCustomerReturn($query, $customerReturnId)
    {
        return $query->where('customer_return_id', $customerReturnId);
    }

    public function scopeByItem($query, $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByItemCode($query, $itemCode)
    {
        return $query->where('item_code', $itemCode);
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
