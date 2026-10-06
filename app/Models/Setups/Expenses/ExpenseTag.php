<?php

namespace App\Models\Setups\Expenses;

use App\Traits\Authorable;
use App\Traits\InvalidatesCacheVersion;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseTag extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable, InvalidatesCacheVersion;

    protected static string $cacheVersionKey = 'expense_tags';

    /** code => display name. Fixed set. Add a line to introduce a new tag. */
    public const SYSTEM_TAGS = [
        'rent'       => 'Rent',
        'utilities'  => 'Utilities',
        'equipments' => 'Equipments',
    ];

    protected $fillable = ['name', 'code', 'description', 'is_active', 'is_system'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected $searchable = ['name', 'description'];
    protected $sortable = ['id', 'name', 'code', 'is_active', 'created_at', 'updated_at'];
    protected $defaultSortField = 'name';
    protected $defaultSortDirection = 'asc';

    /** Idempotent. Inserts any missing constant tag. */
    public static function ensureSystemTags(): void
    {
        foreach (self::SYSTEM_TAGS as $code => $name) {
            static::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true],
            );
        }
    }

    /**
     * @return BelongsToMany<ExpenseCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseCategory::class, 'expense_category_expense_tag');
    }

    /**
     * @return array<int, int>
     */
    public function categoryIds(): array
    {
        return $this->categories()->pluck('expense_categories.id')->all();
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query)
    {
        return $query->where('is_system', true);
    }
}
