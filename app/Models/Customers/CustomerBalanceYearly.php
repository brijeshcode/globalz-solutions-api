<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBalanceYearly extends Model
{
    protected $fillable = [
        'customer_id',
        'year',
        'total_sale',
        'total_sale_amount',
        'total_return',
        'total_return_amount',
        'total_credit',
        'total_credit_amount',
        'total_debit',
        'total_debit_amount',
        'total_payment',
        'total_payment_amount',
        'transaction_total',
        'closing_balance',
        
    ];

    protected $casts = [
        'year' => 'integer',
        'total_sale' => 'integer',
        'total_sale_amount' => 'decimal:2',
        'total_return' => 'integer',
        'total_return_amount' => 'decimal:2',
        'total_credit' => 'integer',
        'total_credit_amount' => 'decimal:2',
        'total_debit' => 'integer',
        'total_debit_amount' => 'decimal:2',
        'total_payment' => 'integer',
        'total_payment_amount' => 'decimal:2',
        'transaction_total' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'updated_by_entry_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
