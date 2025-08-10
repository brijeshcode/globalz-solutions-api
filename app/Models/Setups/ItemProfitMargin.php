<?php

namespace App\Models\Setups;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemProfitMargin extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'margin_percentage',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'margin_percentage' => 'decimal:2',
    ];

    protected $searchable = [
        'name',
        'description',
    ];

    protected $sortable = [
        'id',
        'name',
        'margin_percentage',
        'description',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'name';
    protected $defaultSortDirection = 'asc';
}