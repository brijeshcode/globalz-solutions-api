<?php

namespace App\Models\Setups\Employees;

use App\Models\Employees\Employee;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\InvalidatesCacheVersion;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, Searchable, Sortable, InvalidatesCacheVersion;

    protected static string $cacheVersionKey = 'department';

    public const FIXDEPARTMENTS = [ 'Warehouse', 'Sales', 'Accounting', 'Administration', 'Shipping'];

    public static function getDefaultDepartments(): array
    {
        return config('app.default_departments', [
            'Sales',
            'Accounting',
            'Shipping',
            'Administration',
            'Warehouse'
        ]);
    }
    
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

    protected static function booted(): void
    {
        static::updating(function (Department $department) {
            if ($department->isDirty('name') && in_array($department->getOriginal('name'), self::getDefaultDepartments())) {
                throw new \Exception('Cannot update the name of a default department: ' . $department->getOriginal('name'));
            }
        });

        static::deleting(function (Department $department) {
            if (in_array($department->name, self::getDefaultDepartments())) {
                throw new \Exception('Cannot delete default department: ' . $department->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
