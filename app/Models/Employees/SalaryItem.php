<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryItem extends Model
{
    protected $fillable = [
        'salary_id',
        'label',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }
}
