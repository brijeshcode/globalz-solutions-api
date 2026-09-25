<?php

namespace App\Helpers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;

class CustomersHelper {

    /**
     * Whether child customer balances are folded into their parent.
     * Developer-managed landlord feature flag (per tenant), default off.
     */
    public static function combineEnabled(): bool
    {
        return FeatureHelper::isCombineParentChildBalance();
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

    /**
     * Canonical recompute of a customer's current_balance per the combine rule.
     * The single source of truth for full balance recalculation.
     * Returns the persisted balance.
     */
    public static function recalculateCurrentBalance(Customer $customer): float
    {
        $combine = self::combineEnabled();

        if ($combine && $customer->hasParent()) {
            $balance = 0.0; // child's balance is owned by the parent
        } else {
            $ids = [$customer->id];
            if ($combine) {
                $ids = array_merge($ids, $customer->children()->pluck('id')->all());
            }
            $balance = self::sumBalanceForCustomerIds($ids);
        }

        if ((float) $customer->current_balance != $balance) {
            $customer->update(['current_balance' => $balance]);
        }

        return $balance;
    }

    /**
     * Canonical balance formula, DB-level aggregation.
     * balance = (payments + returns + credit_notes) - (sales + debit_notes)
     * Positive = we owe customer (credit); negative = customer owes us (debit).
     */
    public static function sumBalanceForCustomerIds(array $ids): float
    {
        $sales    = (float) Sale::approved()->whereIn('customer_id', $ids)->sum('total_usd');
        $returns  = (float) CustomerReturn::approved()->received()->whereIn('customer_id', $ids)->sum('total_usd');
        $payments = (float) CustomerPayment::approved()->whereIn('customer_id', $ids)->sum('amount_usd');
        $credit   = (float) CustomerCreditDebitNote::where('type', 'credit')->whereIn('customer_id', $ids)->sum('amount_usd');
        $debit    = (float) CustomerCreditDebitNote::where('type', 'debit')->whereIn('customer_id', $ids)->sum('amount_usd');

        return ($payments + $returns + $credit) - ($sales + $debit);
    }
}
