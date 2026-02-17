<?php

namespace App\Models\Setups;

use App\Models\Items\Item;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxCode extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'tax_percent',
        'type',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'tax_percent' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'name',
        'description',
    ];

    protected $sortable = [
        'id',
        'code',
        'name',
        'tax_percent',
        'type',
        'is_active',
        'is_default',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'code';
    protected $defaultSortDirection = 'asc';

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors & Mutators
    public function getTaxRateAttribute(): float
    {
        return (float) ($this->tax_percent / 100);
    }

    // Helper Methods
    public function calculateTax(float $amount): float
    {
        if ($this->type === 'inclusive') {
            // Tax is included in the amount: tax = amount - (amount / (1 + tax_rate))
            return $amount - ($amount / (1 + $this->tax_rate));
        } else {
            // Tax is exclusive: tax = amount * tax_rate
            return $amount * $this->tax_rate;
        }
    }

    public function calculateTaxAmount(float $baseAmount): float
    {
        return $baseAmount * $this->tax_rate;
    }

    public function calculateTotalWithTax(float $baseAmount): float
    {
        if ($this->type === 'inclusive') {
            // Amount already includes tax
            return $baseAmount;
        } else {
            // Add tax to base amount
            return $baseAmount + $this->calculateTaxAmount($baseAmount);
        }
    }

    public function calculateBaseFromTotal(float $totalAmount): float
    {
        if ($this->type === 'inclusive') {
            // Remove tax from total: base = total / (1 + tax_rate)
            return $totalAmount / (1 + $this->tax_rate);
        } else {
            // Total doesn't include tax, so base = total
            return $totalAmount;
        }
    }

    // Static Methods
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    public static function getByCode(string $code): ?self
    {
        return static::where('code', $code)->where('is_active', true)->first();
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}