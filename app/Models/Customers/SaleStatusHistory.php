<?php

namespace App\Models\Customers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleStatusHistory extends Model
{
    protected $fillable = ['sale_id', 'status', 'changed_by'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
