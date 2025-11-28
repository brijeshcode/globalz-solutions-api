<?php

namespace App\Models\Employees;

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

    public function commissionTarget(): BelongsTo
    {
        return $this->belongsTo(CommissionTarget::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
