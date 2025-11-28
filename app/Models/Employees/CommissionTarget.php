<?php

namespace App\Models\Employees;

use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionTarget extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\CommissionTargetFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable;

    protected $fillable = [
        'code',
        'prefix',
        'date', 
        'name',
        'note',
        'active'
    ];

    protected $casts = [
        'date' => 'datetime', 
        'is_active' => 'boolean',
    ];

    
    protected $searchable = [
        'code',
        'name',
        'note',
    ];

    protected $sortable = [
        'id',
        'code',
        'date', 
        'name',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function rules(): HasMany
    {
        return $this->hasMany(CommissionTargetRule::class);
    }

    public function employeeCommissionTargets(): HasMany
    {
        return $this->hasMany(EmployeeCommissionTarget::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    // Scopes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByPrefix($query, $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    // Helper Methods
    public function getCommissionTargetCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.commission_target_code_start', 1000);
        $newValue = Setting::incrementValue('commission_targets', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setCommissionTargetCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($commissionTarget) {
            if (!$commissionTarget->code) {
                $commissionTarget->setCommissionTargetCode();
            }

            $commissionTarget->prefix = 'COMTAR';
        });
    }
}
