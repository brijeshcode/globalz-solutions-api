<?php

namespace App\Services\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerBalanceMonthly;
use App\Models\Customers\CustomerBalanceYearly;
use App\Models\Customers\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBalanceService
{
    public static function latestClosingBalance(int $customerId): float
    {
        $latestBalance = CustomerBalanceMonthly::select('closing_balance')
            ->where('transaction_total', '>', 0)
            ->where('customer_id', $customerId)
            ->where('closing_balance' , '>', 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        return $latestBalance ? (float) $latestBalance->closing_balance : 0.0;
    }

    public static function currentTransactionTotal(int $customerId): float
    {
        $latestBalance = CustomerBalanceMonthly::select('transaction_total')
            ->where('customer_id', $customerId)
            ->where('closing_balance' , 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        return $latestBalance ? (float) $latestBalance->transaction_total : 0.0;
    }

    /**
     * Update customer balance for the current month based on transaction
     *
     * @param int $customerId
     * @param string $transactionType - 'sale', 'return', 'credit', 'debit', 'payment'
     * @param float $usdAmount
     * @param int $entryId - ID of the transaction entry
     * @param int $quantity - quantity of items (for sales/returns)
     * @return void
     */
    public static function updateMonthlyTotal(int $customerId, string $transactionType, float $usdAmount, int $entryId, int $quantity = 1): void
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        try {
            DB::transaction(function () use ($customerId, $transactionType, $usdAmount, $entryId, $quantity, $currentMonth, $currentYear) {
                // Get or create monthly balance record
                $monthlyBalance = CustomerBalanceMonthly::firstOrCreate(
                    [
                        'customer_id' => $customerId,
                        'year' => $currentYear,
                        'month' => $currentMonth,
                    ],
                    [
                        'total_sale' => 0,
                        'total_sale_amount' => 0,
                        'total_return' => 0,
                        'total_return_amount' => 0,
                        'total_credit' => 0,
                        'total_credit_amount' => 0,
                        'total_debit' => 0,
                        'total_debit_amount' => 0,
                        'total_payment' => 0,
                        'total_payment_amount' => 0,
                        'transaction_total' => 0,
                        'closing_balance' => 0,
                        'last_updated_by' => $transactionType,
                        'updated_by_entry_id' => $entryId,
                    ]
                );

                // Update based on transaction type
                self::updateBalanceByType($monthlyBalance, $transactionType, $usdAmount, $quantity, $entryId);

                // Recalculate transaction total and closing balance
                self::calculateBalance($monthlyBalance);

                $monthlyBalance->save();

                // Update customer's current balance
                self::updateCustomerCurrentBalance($customerId);
            });

            // Log::info("Monthly balance updated for customer {$customerId}, type: {$transactionType}, amount: {$usdAmount}");

        } catch (\Exception $e) {
            Log::error("Failed to update monthly balance: " . $e->getMessage());
            throw $e;
        }
    }

    private static function updateCustomerCurrentBalance(int $customerId): void
    {
        // Sum all monthly closing balances for the customer
        $totalBalance = self::currentTransactionTotal($customerId) + self::latestClosingBalance($customerId);

        // Update customer's current balance
        $customer = Customer::find($customerId);
        if($customer->current_balance != $totalBalance){
            $customer->current_balance = $totalBalance;
            $customer->save();
        }
    }

    /**
     * Update balance fields based on transaction type
     */
    private static function updateBalanceByType(CustomerBalanceMonthly $balance, string $transactionType, float $amount, int $quantity, int $entryId): void
    {
        switch ($transactionType) {
            case 'sale':
                $balance->total_sale += $quantity;
                $balance->total_sale_amount += $amount;
                break;
            case 'return':
                $balance->total_return += $quantity;
                $balance->total_return_amount += $amount;
                break;
            case 'credit':
                $balance->total_credit += $quantity;
                $balance->total_credit_amount += $amount;
                break;
            case 'debit':
                $balance->total_debit += $quantity;
                $balance->total_debit_amount += $amount;
                break;
            case 'payment':
                $balance->total_payment += $quantity;
                $balance->total_payment_amount += $amount;
                break;
            default:
                throw new \InvalidArgumentException("Invalid transaction type: {$transactionType}");
        }

        $balance->last_updated_by = $transactionType;
        $balance->updated_by_entry_id = $entryId;
    }  

    // this will be use in scheduler every end of the month
    public static function endOfMonthCalculation(int $customerId): void
    {
        // here we first fetch last closing balance, 
        $previousMonthBalance = self::latestClosingBalance($customerId);

        // fetch all amount from different transaction table
        $balance = CustomerBalanceMonthly::where('customer_id', $customerId)
            ->where('closing_balance' , '>', 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        // sum it to transaction_total 

        self::calculateBalance($balance);
        
            // update closing_balance = transaction_total + latest closing balance
        $balance->closing_balance = $previousMonthBalance + $balance->transaction_total;

        $balance->save();
        
        $customer = Customer::find($customerId);
        $customer->current_balance = $balance->closing_balance;
    }

    private static function calculateBalance(CustomerBalanceMonthly $balance): void
    {
        $balance->transaction_total =
            $balance->total_payment_amount 
            + $balance->total_credit_amount 
            + $balance->total_return_amount 
            - $balance->total_sale_amount 
            - $balance->total_debit_amount 
            ;
    }

    private function getSaleTotal($customerId, $month, $year): float 
    {
        return Sale::where('customer_id', $customerId)->sum('total_usd');
    }
}