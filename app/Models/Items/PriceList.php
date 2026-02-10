<?php

namespace App\Models\Items;

use App\Models\Customers\Customer;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    /** @use HasFactory<\Database\Factories\Items\PriceListFactory> */
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'code',
        'description',
        'is_default',
        'item_count',
        'note',
    ];

    protected $casts = [
        'item_count' => 'integer',
        'is_default' => 'boolean'
    ];

    protected $searchable = [
        'code',
        'description',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'description',
        'item_count',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function customersInv(): HasMany
    {
        return $this->hasMany(Customer::class, 'price_list_id_INV');
    }

    public function customersInx(): HasMany
    {
        return $this->hasMany(Customer::class, 'price_list_id_INX');
    }

    // Scopes
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Static Methods
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    // Helper Methods
    public function updateItemCount(): void
    {
        $this->updateQuietly([
            'item_count' => $this->items()->count(),
        ]);
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($priceList) {
            if (!$priceList->item_count) {
                $priceList->item_count = 0;
            }
        });

        static::deleted(function ($priceList) {
            // Delete all associated price list items when price list is deleted
            $priceList->items()->delete();
        });
    }
}
