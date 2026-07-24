<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTargetRule extends Model
{
    /** @use HasFactory<\Database\Factories\Employees\CommissionTargetRuleFactory> */
    use HasFactory;
    public const TYPE_FUEL = 'fuel';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_SALE = 'sale';

    public const TYPES = [
        self::TYPE_FUEL,
        self::TYPE_PAYMENT,
        self::TYPE_SALE,
    ];

    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY = 'yearly';

    public const PERIODS = [
        self::PERIOD_MONTHLY,
        self::PERIOD_YEARLY,
    ];

    public const INCLUDE_TYPE = ['Own', 'All', 'All except own'];
    public const INCLUDE_TYPE_OWN = 'Own';
    public const INCLUDE_TYPE_ALL = 'All';
    public const INCLUDE_TYPE_ALL_EXCEPT_OWN = 'All except own';

    public const AMOUNT_TYPE = ['set', 'auto'];
    public const AMOUNT_TYPE_AUTO = 'auto';
    public const AMOUNT_TYPE_SET = 'set';

    public const REWARD_CALCULATION_TYPE = ['fixed', 'dynamic'];
    public const REWARD_CALCULATION_TYPE_FIXED = 'fixed';
    public const REWARD_CALCULATION_TYPE_DYNAMIC = 'dynamic';

    public const REWARD_TYPE = ['fixed', 'percent'];
    public const REWARD_TYPE_FIXED = 'fixed';
    public const REWARD_TYPE_PERCENT = 'percent';

    protected $fillable = [
        'commission_target_id',
        'type',
        'period',
        'include_type',
        'amount_type',
        'number_of_months',
        'push_target_percent',
        'minimum_amount',
        'maximum_amount',
        'reward_type',
        'percent',
        'fixed_reward',
        'reward_calculation_type',
        'comission_label',
    ];

    protected $casts = [
        'minimum_amount'      => 'decimal:4',
        'maximum_amount'      => 'decimal:4',
        'percent'             => 'decimal:4',
        'fixed_reward'        => 'decimal:4',
        'push_target_percent' => 'decimal:2',
        'number_of_months'    => 'integer',
    ];

    // Relationships
    public function commissionTarget(): BelongsTo
    {
        return $this->belongsTo(CommissionTarget::class);
    }

    // Scopes
    public function scopeByCommissionTarget(Builder $query, int $commissionTargetId)
    {
        return $query->where('commission_target_id', $commissionTargetId);
    }

    public function scopeByType(Builder $query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByRewardCalculationType(Builder $query, string $type)
    {
        return $query->where('reward_calculation_type', $type);
    }

    public function scopeInAmountRange(Builder $query, float $amount)
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
