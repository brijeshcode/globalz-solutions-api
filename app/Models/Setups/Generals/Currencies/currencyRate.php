<?php

namespace App\Models\Setups\Generals\Currencies;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class currencyRate extends Model
{
    /** @use HasFactory<\Database\Factories\Setups\Generals\Currencies\currencyRateFactory> */
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;
    protected $fillable = [
        'currency_id',
        'rate',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rate' => 'decimal:6',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $defaultSortField = 'currency_id';
    protected $defaultSortDirection = 'desc';

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}