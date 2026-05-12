<?php

namespace App\Models\Vehicle;

use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Vehicle\CarRefillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarRefill extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'date', 'code', 'car_id', 'gas_station_id', 'driver_id',
        'odometer', 'km_driven', 'amount', 'amount_usd', 'currency_id', 'currency_rate',
        'invoices_count', 'note',
    ];

    protected $searchable = ['code', 'note'];

    protected $casts = [
        'date'           => 'datetime',
        'odometer'       => 'decimal:2',
        'km_driven'      => 'decimal:2',
        'amount'         => 'decimal:4',
        'amount_usd'     => 'decimal:8',
        'currency_rate'  => 'decimal:4',
        'invoices_count' => 'integer',
    ];

    protected static function newFactory(): CarRefillFactory
    {
        return CarRefillFactory::new();
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function driver()
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public static function generateNextCode(): string
    {
        $number = Setting::getOrCreateCounter('car_refills', 'code_counter', 1000);
        return 'KM' . $number;
    }

    public static function reserveNextCode(): string
    {
        $number = Setting::incrementValue('car_refills', 'code_counter', 1, 1000);
        return 'KM' . ($number - 1);
    }

    public function calculateKmDriven(): float
    {
        $previous = self::where('car_id', $this->car_id)
            ->where(function ($q) {
                $q->where('date', '<', $this->date)
                  ->orWhere(function ($q2) {
                      $q2->where('date', '=', $this->date)->where('id', '<', $this->id ?? PHP_INT_MAX);
                  });
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->value('odometer');

        if ($previous === null) {
            return 0;
        }

        return max(0, (float) $this->odometer - (float) $previous);
    }

    public function recalculateNextRefill(): void
    {
        $next = self::where('car_id', $this->car_id)
            ->where(function ($q) {
                $q->where('date', '>', $this->date)
                  ->orWhere(function ($q2) {
                      $q2->where('date', '=', $this->date)->where('id', '>', $this->id);
                  });
            })
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if ($next) {
            $next->km_driven = $next->calculateKmDriven();
            $next->saveQuietly();
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CarRefill $refill) {
            if (!$refill->code) {
                $refill->code = self::reserveNextCode();
            }
            $refill->km_driven = $refill->calculateKmDriven();
        });

        static::created(function (CarRefill $refill) {
            $refill->gasStation()->increment('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::deleted(function (CarRefill $refill) {
            $refill->gasStation()->decrement('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::restored(function (CarRefill $refill) {
            $refill->gasStation()->increment('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });
    }
}
