<?php

namespace App\Models\Vehicle;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Traits\Authorable;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Database\Factories\Vehicle\GasStationPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GasStationPayment extends Model
{
    use HasFactory, SoftDeletes, Authorable, Searchable, Sortable;

    protected $fillable = [
        'date', 'code', 'gas_station_id', 'account_id',
        'amount', 'amount_usd', 'currency_id', 'currency_rate', 'note',
    ];

    protected $searchable = ['code', 'note'];

    protected $casts = [
        'date'          => 'datetime',
        'amount'        => 'decimal:4',
        'amount_usd'    => 'decimal:8',
        'currency_rate' => 'decimal:4',
    ];

    protected static function newFactory(): GasStationPaymentFactory
    {
        return GasStationPaymentFactory::new();
    }

    public function gasStation()
    {
        return $this->belongsTo(GasStation::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public static function generateNextCode(): string
    {
        $number = Setting::getOrCreateCounter('gas_station_payments', 'code_counter', 100);
        return 'GS' . $number;
    }

    public static function reserveNextCode(): string
    {
        $number = Setting::incrementValue('gas_station_payments', 'code_counter', 1, 100);
        return 'GS' . ($number - 1);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (GasStationPayment $payment) {
            if (!$payment->code) {
                $payment->code = self::reserveNextCode();
            }
        });

        static::created(function (GasStationPayment $payment) {
            $payment->gasStation()->decrement('balance', $payment->amount);
        });

        static::deleted(function (GasStationPayment $payment) {
            $payment->gasStation()->increment('balance', $payment->amount);
        });

        static::restored(function (GasStationPayment $payment) {
            $payment->gasStation()->decrement('balance', $payment->amount);
        });
    }
}
