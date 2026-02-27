<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class CapitalSnapshot extends Model
{
    protected $fillable = [
        'year',
        'month',
        'available_stock_value',
        'vat_on_current_stock',
        'pending_purchases_value',
        'net_stock_value',
        'unpaid_customer_balance',
        'unapproved_payments',
        'accounts_balance',
        'net_capital',
        'debt_account',
        'final_result',
    ];

    protected $casts = [
        'available_stock_value' => 'decimal:2',
        'vat_on_current_stock' => 'decimal:2',
        'pending_purchases_value' => 'decimal:2',
        'net_stock_value' => 'decimal:2',
        'unpaid_customer_balance' => 'decimal:2',
        'unapproved_payments' => 'decimal:2',
        'accounts_balance' => 'decimal:2',
        'net_capital' => 'decimal:2',
        'debt_account' => 'decimal:2',
        'final_result' => 'decimal:2',
    ];
}
