<?php

namespace App\Models\Items;

use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceListBulkUpdate extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'note',
        'filters',
        'item_count',
        'price_list_count',
    ];

    protected $casts = [
        'date' => 'date',
        'filters' => 'array',
        'item_count' => 'integer',
        'price_list_count' => 'integer',
    ];

    protected $searchable = [
        'note',
    ];

    protected $sortable = [
        'id',
        'date',
        'item_count',
        'price_list_count',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(PriceListBulkUpdateItem::class, 'bulk_update_id');
    }

    // Helper Methods
    public function updateCounts(): void
    {
        $this->updateQuietly([
            'item_count' => $this->items()->count(),
            'price_list_count' => $this->items()->distinct('price_list_id')->count('price_list_id'),
        ]);
    }
}
