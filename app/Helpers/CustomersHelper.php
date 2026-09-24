<?php

namespace App\Helpers;

use App\Models\Customers\Customer;
use App\Models\Setting;

class CustomersHelper {

    public static function combineEnabled(): bool
    {
        return (bool) Setting::get(
            'customers', 'combine_parent_child_balance', false, true, Setting::TYPE_BOOLEAN
        );
    }

    /**
     * The customer whose current_balance a transaction should affect.
     * When combining is enabled, a child's balance is owned by its parent.
     */
    public static function balanceOwner(Customer $customer): Customer
    {
        return (self::combineEnabled() && $customer->hasParent())
            ? ($customer->parent()->first() ?? $customer)
            : $customer;
    }

    public static function addBalance( Customer $customer, float $balance): void
    {
        $target = self::balanceOwner($customer);
        $target->current_balance += $balance;
        $target->save();
    }

    public static function removeBalance( Customer $customer, float $balance): void
    {
        $target = self::balanceOwner($customer);
        $target->current_balance -= $balance;
        $target->save();
    }
}
