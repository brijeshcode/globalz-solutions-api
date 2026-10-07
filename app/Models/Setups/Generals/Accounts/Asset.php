<?php

namespace App\Models\Setups\Generals\Accounts;

use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\Setups\generals\Accounts\assetFactory> */
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'name',
        'date',
        'quantity',
        'currency_id',
        'currency_rate',
        'amount',
        'amount_usd',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
        'currency_rate' => 'decimal:4',
        'amount' => 'decimal:6',
        'amount_usd' => 'decimal:6',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';

    /**
     * @return BelongsTo<\App\Models\Setups\Generals\Currencies\Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Setups\Generals\Currencies\Currency::class);
    }
}
