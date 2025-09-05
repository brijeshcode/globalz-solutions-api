<?php

namespace App\Models\Setups\Generals\Currencies;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;

class Currency extends Model
{
    /** @use HasFactory<\Database\Factories\Setups\CurrencyFactory> */
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'code',
        'symbol',
        'symbol_position',
        'decimal_places',
        'decimal_separator',
        'thousand_separator',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Helper methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function activate()
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate()
    {
        return $this->update(['is_active' => false]);
    }

    // Format amount with currency
    public function formatAmount($amount)
    {
        $formattedAmount = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousand_separator
        );

        if ($this->symbol_position === 'before') {
            return $this->symbol . $formattedAmount;
        }

        return $formattedAmount . $this->symbol;
    }

    // Get display name with code
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->code . ')';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}