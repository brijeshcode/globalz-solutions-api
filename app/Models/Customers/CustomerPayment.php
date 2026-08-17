<?php

namespace App\Models\Customers;

use App\Contracts\ModuleLockable;
use App\Helpers\AccountsHelper;
use App\Helpers\CustomersHelper;
use App\Models\Accounts\Account;
use App\Models\Employees\Employee;
use Carbon\CarbonInterface;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\SyncableToOld;
use App\Traits\TracksActivity;
use App\Traits\HasDateWithTime;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model implements ModuleLockable
{
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, Searchable, Sortable, TracksActivity, HasDateFilters, SyncableToOld;

    public const TAXPREFIX = 'RCT';
    public const TAXFREEPREFIX = 'RCX';

    protected $fillable = [
        'date',
        'prefix',
        'code',
        'customer_id',
        'salesperson_id',
        'customer_payment_term_id',
        'currency_id',
        'currency_rate',
        'amount',
        'amount_usd',
        'credit_limit',
        'last_payment_amount',
        'rtc_book_number',
        'note',
        'approved_by',
        'approved_at',
        'account_id',
        'approve_note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'currency_rate' => 'float',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'last_payment_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $searchable = [
        'code',
        'rtc_book_number',
        'note',
        'approve_note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'customer_id',
        'currency_id',
        'amount',
        'amount_usd',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'date';
    protected $defaultSortDirection = 'desc';

    // Relationships
    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'salesperson_id');
    }

    /**
     * @return BelongsTo<CustomerPaymentTerm, $this>
     */
    public function customerPaymentTerm(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentTerm::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Define which attributes to log for activity tracking
     */
    protected function getActivityLogAttributes(): array
    {
        return [
            'customer_id',
            'account_id',
            'currency_id',
            'currency_rate',
            'amount',
            'amount_usd',
            'rtc_book_number',
            'note',
        ];
    }

    // we will not log un-approved payments 
    protected function shouldSkipActivityLog(): bool
    {
        return is_null($this->approved_at);
    }

    // Scopes
    public function scopeApproved(Builder $query)
    {
        return $query->whereNotNull('approved_by');
    }

    public function scopePending(Builder $query)
    {
        return $query->whereNull('approved_by');
    }

    public function scopeByCustomer(Builder $query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySalesperson(Builder $query, int $salespersonId)
    {
        return $query->where('salesperson_id', $salespersonId);
    }

    public function scopeByCurrency(Builder $query, int $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDateRange(Builder $query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode(Builder $query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByPrefix(Builder $query, string $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    // Helper Methods
    public function isApproved(): bool
    {
        return !is_null($this->approved_by);
    }

    public function isPending(): bool
    {
        return is_null($this->approved_by);
    }

    public function getPaymentCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.customer_payment_code_start', 1000);
        $newValue = Setting::incrementValue('customer_payments', 'code_counter', 1, $defaultValue);
        return str_pad((string) $newValue, 6, '0', STR_PAD_LEFT);
    }

    public function setPaymentCode(): string
    {
        return $this->code = self::reserveNextCode();
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->code) {
                $payment->setPaymentCode();
            }

            // Snapshot the customer's salesperson at creation so commission crediting stays
            // fixed even if the customer is later reassigned. Only set when not provided.
            if (is_null($payment->salesperson_id) && $payment->customer_id) {
                $payment->salesperson_id = Customer::whereKey($payment->customer_id)->value('salesperson_id');
            }
        });

        static::created(function ($payment) {
            // Only update balances if payment is approved
            if ($payment->isApproved()) {
                // Add to account balance
                AccountsHelper::addBalance(Account::find($payment->account_id), $payment->amount_usd);
                // Add to customer balance (payment reduces what customer owes)
                CustomersHelper::addBalance(Customer::find($payment->customer_id), $payment->amount_usd);
            }
        });

        static::updating(function ($payment) {
            // If the payment is moved to a different customer, re-snapshot the salesperson from
            // the new customer — unless the editor explicitly set a salesperson in the same edit.
            if ($payment->isDirty('customer_id') && !$payment->isDirty('salesperson_id') && $payment->customer_id) {
                $payment->salesperson_id = Customer::whereKey($payment->customer_id)->value('salesperson_id');
            }
        });

        static::updated(function ($payment) {
            $original = $payment->getOriginal();
            $wasApproved = !is_null($original['approved_by']);
            $isNowApproved = $payment->isApproved();

            // Case 1: Payment just got approved (was pending, now approved)
            if (!$wasApproved && $isNowApproved) {
                AccountsHelper::addBalance(Account::find($payment->account_id), $payment->amount_usd);
                CustomersHelper::addBalance(Customer::find($payment->customer_id), $payment->amount_usd);
            }
            // Case 2: Payment was unapproved (was approved, now pending)
            elseif ($wasApproved && !$isNowApproved) {
                AccountsHelper::removeBalance(Account::find($original['account_id']), $original['amount_usd']);
                CustomersHelper::removeBalance(Customer::find($payment->customer_id), $original['amount_usd']);
            }
            // Case 3: Payment is approved and account changed
            elseif ($isNowApproved && $original['account_id'] != $payment->account_id) {
                AccountsHelper::removeBalance(Account::find($original['account_id']), $original['amount_usd']);
                AccountsHelper::addBalance(Account::find($payment->account_id), $payment->amount_usd);
                // Customer balance doesn't change when account changes
            }
            // Case 4: Payment is approved and customer changed
            elseif ($isNowApproved && $original['customer_id'] != $payment->customer_id) {
                CustomersHelper::removeBalance(Customer::find($original['customer_id']), $original['amount_usd']);
                CustomersHelper::addBalance(Customer::find($payment->customer_id), $payment->amount_usd);
                // Account balance doesn't change when customer changes
            }
            // Case 5: Payment is approved and amount changed
            elseif ($isNowApproved && $original['amount_usd'] != $payment->amount_usd) {
                $difference = $payment->amount_usd - $original['amount_usd'];
                AccountsHelper::addBalance(Account::find($payment->account_id), $difference);
                CustomersHelper::addBalance(Customer::find($payment->customer_id), $difference);
            }
        });

        static::deleted(function ($payment) {
            // Only remove balances if payment was approved
            if ($payment->isApproved()) {
                AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount_usd);
                CustomersHelper::removeBalance(Customer::find($payment->customer_id), $payment->amount_usd);
            }
        });
    }

    // Module lock (see App\Contracts\ModuleLockable)
    public function moduleLockKey(): string
    {
        return is_null($this->approved_by) ? 'customer_payment_order' : 'customer_payment';
    }

    public function moduleLockDate(): ?CarbonInterface
    {
        return $this->date;
    }

    public function isModuleLockExempt(): bool
    {
        return false;
    }
}
