<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCommissionTarget extends Model
{
    protected $fillable = [
        'employee_id',
        'commission_target_id',
        'month',
        'year',
        'note',
    ];

    /**
     * @return BelongsTo<CommissionTarget, $this>
     */
    public function commissionTarget(): BelongsTo
    {
        return $this->belongsTo(CommissionTarget::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeByEmployee(Builder $query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
    
    public function scopeByMonth(Builder $query, string | int $month)
    {
        return $query->where('month', $month);
    }

    public function scopeByYear(Builder $query, int $year)
    {
        return $query->where('year', $year);
    }
}
