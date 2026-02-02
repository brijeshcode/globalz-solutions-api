<?php

namespace App\Models\Suppliers;

use App\Helpers\AccountsHelper;
use App\Helpers\SuppliersHelper;
use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Supplier;
use App\Models\Setups\SupplierPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Traits\Authorable;
use App\Traits\HasDateFilters;
use App\Traits\HasDateWithTime;
use App\Traits\HasDocuments;
use App\Traits\Searchable;
use App\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    /** @use HasFactory<\Database\Factories\Suppliers\SupplierPaymentFactory> */
    use HasFactory, SoftDeletes, Authorable, HasDateWithTime, HasDocuments, Searchable, Sortable, HasDateFilters;

    protected $fillable = [
        'date',
        'prefix',
        'code',
        'supplier_id',
        'supplier_payment_term_id',
        'account_id',
        'currency_id',
        'currency_rate',
        'amount',
        'amount_usd',
        'last_payment_amount_usd',
        'supplier_order_number',
        'check_number',
        'bank_ref_number',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'currency_rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'last_payment_amount_usd' => 'decimal:2',
    ];

    protected $searchable = [
        'code',
        'supplier_order_number',
        'check_number',
        'bank_ref_number',
        'note',
    ];

    protected $sortable = [
        'id',
        'date',
        'code',
        'supplier_id',
        'currency_id',
        'amount',
        'amount_usd',
        'created_at',
        'updated_at',
    ];

    protected $defaultSortField = 'id';
    protected $defaultSortDirection = 'desc';

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierPaymentTerm(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentTerm::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // Scopes
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByCurrency($query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByPrefix($query, $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    // Helper Methods
    public function getPaymentCodeAttribute(): string
    {
        return $this->prefix . $this->code;
    }

    // Code Generation Methods
    public static function reserveNextCode(): string
    {
        $defaultValue = config('app.supplier_payment_code_start', 1000);
        $newValue = Setting::incrementValue('supplier_payments', 'code_counter', 1, $defaultValue);
        return str_pad($newValue, 6, '0', STR_PAD_LEFT);
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

            $payment->prefix = 'SPAY';
            $payment->supplier_payment_term_id = Supplier::select('payment_term_id')->find($payment->supplier_id)?->payment_term_id;
        });

        static::created(function ($payment) {
            // Supplier payment removes balance from account (money going out)
            AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount_usd);
            // Supplier payment removes balance from supplier (reduces what we owe them)
            SuppliersHelper::removeBalance(Supplier::find($payment->supplier_id), $payment->amount_usd);
        });

        static::updated(function ($payment) {
            $original = $payment->getOriginal();

            // Update Account Balance
            // Case 1: Account changed
            if ($original['account_id'] != $payment->account_id) {
                // Add balance back to old account
                AccountsHelper::addBalance(Account::find($original['account_id']), $original['amount_usd']);
                // Remove balance from new account
                AccountsHelper::removeBalance(Account::find($payment->account_id), $payment->amount_usd);
            }
            // Case 2: Amount changed on same account
            elseif ($original['amount_usd'] != $payment->amount_usd) {
                $difference = $payment->amount_usd - $original['amount_usd'];
                // If amount increased, remove more; if decreased, add back
                AccountsHelper::removeBalance(Account::find($payment->account_id), $difference);
            }

            // Update Supplier Balance
            // Case 1: Supplier changed
            if ($original['supplier_id'] != $payment->supplier_id) {
                // Add balance back to old supplier (we owe them more again)
                SuppliersHelper::addBalance(Supplier::find($original['supplier_id']), $original['amount_usd']);
                // Remove balance from new supplier (we paid them)
                SuppliersHelper::removeBalance(Supplier::find($payment->supplier_id), $payment->amount_usd);
            }
            // Case 2: Amount changed on same supplier
            elseif ($original['amount_usd'] != $payment->amount_usd) {
                $difference = $payment->amount_usd - $original['amount_usd'];
                // If amount increased, remove more from supplier balance; if decreased, add back
                SuppliersHelper::removeBalance(Supplier::find($payment->supplier_id), $difference);
            }
        });

        static::deleted(function ($payment) {
            // Add balance back to account when payment is deleted
            AccountsHelper::addBalance(Account::find($payment->account_id), $payment->amount_usd);
            // Add balance back to supplier when payment is deleted (we owe them again)
            SuppliersHelper::addBalance(Supplier::find($payment->supplier_id), $payment->amount_usd);
        });
    }
}
