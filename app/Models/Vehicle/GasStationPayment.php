<?php

namespace App\Models\Vehicle;

use App\Helpers\AccountsHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Vehicle\GasStation;
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

    protected $sortable = [
        'id',
        'date',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';
    
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
            AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount);
        });

        static::updated(function (GasStationPayment $payment) {
            $original = $payment->getOriginal();

            // Gas station balance
            if ($original['gas_station_id'] !== $payment->gas_station_id) {
                $payment->gasStation()->decrement('balance', $payment->amount);
                GasStation::find($original['gas_station_id'])?->increment('balance', $original['amount']);
            } elseif ($original['amount'] != $payment->amount) {
                $diff = $payment->amount - $original['amount'];
                $payment->gasStation()->decrement('balance', $diff);
            }

            // Account balance
            if ($original['account_id'] !== $payment->account_id) {
                AccountsHelper::addBalance(Account::find($original['account_id']), $original['amount']);
                AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount);
            } elseif ($original['amount'] != $payment->amount) {
                $diff = $payment->amount - $original['amount'];
                AccountsHelper::removeBalance(Account::find($payment->account_id), $diff);
            }
        });

        static::deleted(function (GasStationPayment $payment) {
            $payment->gasStation()->increment('balance', $payment->amount);
            AccountsHelper::addBalance(Account::find($payment->account_id), $payment->amount);
        });

        static::restored(function (GasStationPayment $payment) {
            $payment->gasStation()->decrement('balance', $payment->amount);
            AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount);
        });
    }
}
