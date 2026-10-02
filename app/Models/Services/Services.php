<?php

namespace App\Models\Services;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\TaxCode;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Services extends Model
{
    /** @use HasFactory<\Database\Factories\Services\ServicesFactory> */
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'name',
        'hsn',
        'tax_code_id',
        'amount',
        'amount_usd',
        'currency_id',
        'currency_rate',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_usd' => 'decimal:8',
        'currency_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    protected $searchable = [
        'name',
        'notes',
    ];

    protected $sortable = [
        'id',
        'name',
        'amount',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }
}
