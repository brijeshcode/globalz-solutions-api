<?php

namespace App\Models\Setups\Customers;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPaymentTerm extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'description',
        'days',
        'type',
        'discount_percentage',
        'discount_days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'days' => 'integer',
        'discount_percentage' => 'decimal:2',
        'discount_days' => 'integer',
    ];

    protected $searchable = [
        'name',
        'description',
        'type',
    ];

    protected $sortable = [
        'id',
        'name',
        'description',
        'days',
        'type',
        'discount_percentage',
        'discount_days',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'name';
    protected $defaultSortDirection = 'asc';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeisActive($query)
    {
        return $query->where('is_active', true);
    }
}