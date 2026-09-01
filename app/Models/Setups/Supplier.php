<?php

namespace App\Models\Setups;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDocuments;
use App\Traits\InvalidatesCacheVersion;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDocuments, Searchable, Sortable, InvalidatesCacheVersion;

    protected static string $cacheVersionKey = 'suppliers';

    protected $fillable = [
        'code',
        'name',
        'supplier_type_id',
        'country_id',
        'opening_balance',
        'current_balance',
        'address',
        'phone',
        'mobile',
        'url',
        'email',
        'contact_person',
        'contact_person_email',
        'contact_person_mobile',
        'payment_term_id',
        'ship_from',
        'origin_type',
        'bank_info',
        'discount_percentage',
        'currency_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'name',
        'email',
        'contact_person',
        'ship_from',
        'notes',
    ];

    protected $sortable = [
        'id',
        'code',
        'name',
        'supplier_type_id',
        'country_id',
        'opening_balance',
        'email',
        'contact_person',
        'payment_term_id',
        'origin_type',
        'currency_id',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'code';
    protected $defaultSortDirection = 'asc';

    // Relationships
    /**
     * @return BelongsTo<SupplierType, $this>
     */
    public function supplierType(): BelongsTo
    {
        return $this->belongsTo(SupplierType::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<SupplierPaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentTerm::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Accessors & Mutators
    public function getBalanceAttribute(): float
    {
        return (float) ($this->current_balance ?? 0);
    }

    // Scopes
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry(Builder $query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeByType(Builder $query, int $typeId)
    {
        return $query->where('supplier_type_id', $typeId);
    }

    protected static function boot()
    {
        parent::boot();
    }
}