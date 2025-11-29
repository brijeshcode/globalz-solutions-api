<?php 

namespace App\Helpers;

use App\Models\Customers\Customer;

class CustomersHelper {

    public static function addBalance( Customer $customer, float $balance): void
    {
        $customer->current_balance += $balance;
        $customer->save();
    } 

    public static function removeBalance( Customer $customer, float $balance): void
    {
        $customer->current_balance -= $balance;
        $customer->save();
    }
}