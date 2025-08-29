<?php

namespace App\Models\Employees;

use App\Models\Setting;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Setups\Employees\Department;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\EmployeeFactory> */
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDocuments, Searchable, Sortable;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'mobile',
        'email',
        'start_date',
        'department_id',
        'is_active',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $searchable = [
        'name',
        'code',
        'phone',
        'mobile',
        'email',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'name',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function zones():BelongsToMany
    {
        return $this->belongsToMany(
            CustomerZone::class,
            'employee_zone_assigns',
            'employee_id',
            'customer_zone_id'
        );
    }

    public static function getCode(): int
    {
        $defaultValue = config('app.employee_code_start', 100);
        return Setting::get('employees', 'code_counter', $defaultValue, true, Setting::TYPE_NUMBER);
    }
    /**
     * Reserve the next code number (increment counter)
     */
    public static function reserveNextCode(): void
    {
        // increment the counter
        Setting::incrementValue('employees', 'code_counter');
    }
}
