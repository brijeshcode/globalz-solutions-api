<?php

namespace App\Models\Setups;

use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\InvalidatesCacheVersion;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemFamily extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable, InvalidatesCacheVersion;

    protected static string $cacheVersionKey = 'item_families';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $searchable = [
        'name',
        'description',
    ];

    protected $sortable = [
        'id',
        'name',
        'description',
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
}