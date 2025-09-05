<?php

namespace App\Models\Setups;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'code',
        'name',
        'supplier_type_id',
        'country_id',
        'opening_balance',
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
        'currency_id',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'code';
    protected $defaultSortDirection = 'asc';

    // Relationships
    public function supplierType(): BelongsTo
    {
        return $this->belongsTo(SupplierType::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentTerm::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Accessors & Mutators
    public function getBalanceAttribute(): float
    {
        // This would calculate current balance based on transactions
        // For now, return opening balance as placeholder
        return (float) ($this->opening_balance ?? 0);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('supplier_type_id', $typeId);
    }

}