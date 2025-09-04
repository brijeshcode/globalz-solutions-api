<?php

namespace App\Models\Accounts;

use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Currency;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable;

    protected $fillable = [
        'name',
        'account_type_id',
        'currency_id',
        'opening_balance',
        'current_balance',
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


    public function currency(): BelongsTo 
    {
        return $this->belongsTo(Currency::class);
    }

    public function accountType(): BelongsTo 
    {
        return $this->belongsTo(AccountType::class);
    }
}