<?php

namespace App\Models\Customers;

use App\Models\User;
use App\Models\Vehicle\Car;
use App\Models\Vehicle\CarRefill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleStatusHistory extends Model
{
    protected $fillable = ['sale_id', 'status', 'changed_by', 'car_id'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        $triggerRefillRecalc = function (SaleStatusHistory $history) {
            if ($history->car_id && $history->status === 'Delivered') {
                $refill = CarRefill::where('car_id', $history->car_id)
                    ->where('date', '>=', $history->created_at ?? now())
                    ->orderBy('date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                $refill?->recalculateSalesStats();
            }
        };

        static::created($triggerRefillRecalc);
        static::updated($triggerRefillRecalc);
        static::deleted($triggerRefillRecalc);
    }
}
