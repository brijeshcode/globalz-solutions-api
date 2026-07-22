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
        'date', 'code', 'filling_auth_code', 'car_id', 'gas_station_id', 'driver_id',
        'odometer', 'km_driven', 'amount', 'amount_usd', 'currency_id', 'currency_rate',
        'invoices_count', 'sales_amount_usd', 'delivery_cost_pct', 'note',
    ];

    protected $searchable = ['code', 'note'];

    protected $casts = [
        'date'           => 'datetime',
        'odometer'       => 'decimal:2',
        'km_driven'      => 'decimal:2',
        'amount'         => 'decimal:4',
        'amount_usd'     => 'decimal:8',
        'currency_rate'  => 'decimal:4',
        'invoices_count'    => 'integer',
        'sales_amount_usd'  => 'decimal:8',
        'delivery_cost_pct' => 'decimal:4',
    ];

    protected $sortable = [
        'id',
        'date',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';


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

    public static function generateFillingAuthCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::withTrashed()->where('filling_auth_code', $code)->exists());

        return $code;
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
            $stats = $next->calculateSalesStats();
            $next->km_driven          = $next->calculateKmDriven();
            $next->invoices_count     = $stats['invoices_count'];
            $next->sales_amount_usd   = $stats['sales_amount_usd'];
            $next->delivery_cost_pct  = $stats['delivery_cost_pct'];
            $next->saveQuietly();
        }
    }

    public function calculateSalesStats(): array
    {
        $previousDate = self::where('car_id', $this->car_id)
            ->where(function ($q) {
                $q->where('date', '<', $this->date)
                  ->orWhere(function ($q2) {
                      $q2->where('date', '=', $this->date)->where('id', '<', $this->id ?? PHP_INT_MAX);
                  });
            })
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->value('date');

        $query = \App\Models\Customers\SaleStatusHistory::query()
            ->join('sales', 'sales.id', '=', 'sale_status_histories.sale_id')
            ->where('sale_status_histories.status', 'Delivered')
            ->where('sale_status_histories.car_id', $this->car_id)
            ->where('sale_status_histories.created_at', '<=', $this->date);

        if ($previousDate) {
            $query->where('sale_status_histories.created_at', '>', $previousDate);
        }

        $result = $query->selectRaw('COUNT(*) as count, COALESCE(SUM(sales.total_usd), 0) as amount_usd')->first();

        $count     = (int) ($result->count ?? 0);
        $amountUsd = (float) ($result->amount_usd ?? 0);
        $refillUsd = (float) ($this->amount_usd ?? 0);
        $costPct   = ($amountUsd > 0 && $refillUsd > 0) ? round($refillUsd / $amountUsd * 100, 4) : null;

        return [
            'invoices_count'    => $count,
            'sales_amount_usd'  => $amountUsd,
            'delivery_cost_pct' => $costPct,
        ];
    }

    public function recalculateSalesStats(): void
    {
        $stats = $this->calculateSalesStats();
        $this->updateQuietly([
            'invoices_count'    => $stats['invoices_count'],
            'sales_amount_usd'  => $stats['sales_amount_usd'],
            'delivery_cost_pct' => $stats['delivery_cost_pct'],
        ]);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CarRefill $refill) {
            if (!$refill->code) {
                $refill->code = self::reserveNextCode();
            }
            if (!$refill->filling_auth_code) {
                $refill->filling_auth_code = self::generateFillingAuthCode();
            }
            $refill->km_driven = $refill->calculateKmDriven();

            $stats = $refill->calculateSalesStats();
            $refill->invoices_count    = $stats['invoices_count'];
            $refill->sales_amount_usd  = $stats['sales_amount_usd'];
            $refill->delivery_cost_pct = $stats['delivery_cost_pct'];
        });

        static::updating(function (CarRefill $refill) {
            if ($refill->isDirty(['date', 'car_id', 'amount_usd'])) {
                $stats = $refill->calculateSalesStats();
                $refill->invoices_count    = $stats['invoices_count'];
                $refill->sales_amount_usd  = $stats['sales_amount_usd'];
                $refill->delivery_cost_pct = $stats['delivery_cost_pct'];
            }
        });

        static::created(function (CarRefill $refill) {
            $refill->gasStation()->decrement('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::deleted(function (CarRefill $refill) {
            $refill->gasStation()->increment('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });

        static::restored(function (CarRefill $refill) {
            $refill->gasStation()->decrement('balance', $refill->amount);
            $refill->recalculateNextRefill();
        });
    }
}
