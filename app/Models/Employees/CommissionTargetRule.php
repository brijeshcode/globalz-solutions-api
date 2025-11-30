<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTargetRule extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\CommissionTargetRuleFactory> */
    use HasFactory;
    public const TYPES= ['fuel', 'payment', 'sale'];
    
    protected $fillable = [
        'commission_target_id',
        'type',
        'minimum_amount',
        'maximum_amount',
        'percent',
        'comission_label',
    ];

    protected $casts = [
        'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4',
        'percent' => 'decimal:4',
    ];

    // Relationships
    public function commissionTarget(): BelongsTo
    {
        return $this->belongsTo(CommissionTarget::class);
    }

    // Scopes
    public function scopeByCommissionTarget($query, $commissionTargetId)
    {
        return $query->where('commission_target_id', $commissionTargetId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInAmountRange($query, $amount)
    {
        return $query->where('minimum_amount', '<=', $amount)
                     ->where('maximum_amount', '>=', $amount);
    }

    // Helper Methods
    public function isInRange(float $amount): bool
    {
        return $amount >= $this->minimum_amount && $amount <= $this->maximum_amount;
    }

    // public function calculateCommission(float $amount): float
    // {
    //     if (!$this->isInRange($amount)) {
    //         return 0;
    //     }

    //     return $amount * ($this->percent / 100);
    // }
}
