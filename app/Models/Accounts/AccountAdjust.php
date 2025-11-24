<?php

namespace App\Models\Accounts;

use App\Helpers\AccountsHelper;
use App\Traits\Authorable;
use App\Traits\HasBooleanFilters;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountAdjust extends Model
{
    use HasFactory, SoftDeletes, Authorable, HasBooleanFilters, HasDateWithTime, Searchable, Sortable;

    protected $fillable = [
        'date',
        'code',
        'prefix',
        'type',
        'account_id',
        'amount',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'note',
        'amount',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $settingKey = 'account_adjusts';
        $defaultValue = config('app.account_adjust_code_start', 1000);
        $newValue = \App\Models\Setting::incrementValue($settingKey, 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setTransferCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    public function getTransferCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($adjust) {
            if (!$adjust->code) {
                $adjust->setTransferCode();
            }
            $adjust->prefix = 'ADJ';
        });

        static::created(function ($adjust) {
            $account = Account::find($adjust->account_id);

            if (!$account) {
                return;
            }

            // Credit increases balance, Debit decreases balance
            if ($adjust->type === 'Credit') {
                AccountsHelper::addBalance($account, $adjust->amount);
            } else {
                AccountsHelper::removeBalance($account, $adjust->amount);
            }
        });

        static::updated(function ($adjust) {
            $original = $adjust->getOriginal();

            // If account changed, reverse the original adjustment and apply to new account
            if ($original['account_id'] != $adjust->account_id) {
                $oldAccount = Account::find($original['account_id']);
                $newAccount = Account::find($adjust->account_id);

                if ($oldAccount) {
                    // Reverse the original adjustment
                    if ($original['type'] === 'Credit') {
                        AccountsHelper::removeBalance($oldAccount, $original['amount']);
                    } else {
                        AccountsHelper::addBalance($oldAccount, $original['amount']);
                    }
                }

                if ($newAccount) {
                    // Apply new adjustment
                    if ($adjust->type === 'Credit') {
                        AccountsHelper::addBalance($newAccount, $adjust->amount);
                    } else {
                        AccountsHelper::removeBalance($newAccount, $adjust->amount);
                    }
                }
            }
            // If type changed, reverse original and apply new
            elseif ($original['type'] != $adjust->type) {
                $account = Account::find($adjust->account_id);

                if ($account) {
                    // Reverse original adjustment
                    if ($original['type'] === 'Credit') {
                        AccountsHelper::removeBalance($account, $original['amount']);
                    } else {
                        AccountsHelper::addBalance($account, $original['amount']);
                    }

                    // Apply new adjustment
                    if ($adjust->type === 'Credit') {
                        AccountsHelper::addBalance($account, $adjust->amount);
                    } else {
                        AccountsHelper::removeBalance($account, $adjust->amount);
                    }
                }
            }
            // If only amount changed, adjust the difference
            elseif ($original['amount'] != $adjust->amount) {
                $account = Account::find($adjust->account_id);

                if ($account) {
                    $difference = $adjust->amount - $original['amount'];

                    if ($adjust->type === 'Credit') {
                        AccountsHelper::addBalance($account, $difference);
                    } else {
                        AccountsHelper::removeBalance($account, $difference);
                    }
                }
            }
        });

        static::deleted(function ($adjust) {
            $account = Account::find($adjust->account_id);

            if (!$account) {
                return;
            }

            // Reverse the adjustment: Credit becomes removal, Debit becomes addition
            if ($adjust->type === 'Credit') {
                AccountsHelper::removeBalance($account, $adjust->amount);
            } else {
                AccountsHelper::addBalance($account, $adjust->amount);
            }
        });
    }
}
