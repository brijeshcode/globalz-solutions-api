<?php

namespace App\Services\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerBalanceMonthly;
use App\Models\Customers\CustomerBalanceYearly;
use App\Models\Customers\Sale;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerCreditDebitNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBalanceService
{
    /**
     * Unified customer balance calculation method
     * This is the single source of truth for all balance calculations
     *
     * @param int $customerId - Customer ID
     * @param string $mode - Calculation mode: 'full_rebuild', 'incremental'
     * @param array $options - Additional options:
     *                         - months_back: int (for incremental mode, default 3)
     *                         - update_db: bool (whether to update database, default true)
     * @return array - Balance calculation results
     */
    public static function calculateCustomerBalance(
        int $customerId,
        string $mode = 'incremental',
        array $options = []
    ): array
    {
        $validModes = ['full_rebuild', 'incremental'];
        if (!in_array($mode, $validModes)) {
            throw new \InvalidArgumentException("Invalid mode: {$mode}. Must be one of: " . implode(', ', $validModes));
        }
        $monthsBack = $options['months_back'] ?? 3;
        $updateDb = $options['update_db'] ?? true;
        
        $result = [
            'customer_id' => $customerId,
            'mode' => $mode,
            'current_balance' => 0,
            'breakdown' => [
                'total_sales' => 0,
                'total_returns' => 0,
                'total_payments' => 0,
                'total_credit_notes' => 0,
                'total_debit_notes' => 0,
            ],
            'calculated_at' => now()->toDateTimeString(),
        ];
        
       
        try {
            DB::beginTransaction();

            switch ($mode) {
                case 'full_rebuild':
                    $result = array_merge($result, self::performFullRebuild($customerId, $updateDb));
                    break;

                case 'incremental':
                    $result = array_merge($result, self::performIncrementalUpdate($customerId, $monthsBack, $updateDb));
                    break;
            }

            if ($updateDb) {
                DB::commit();
            } else {
                DB::rollBack();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to calculate customer balance", [
                'customer_id' => $customerId,
                'mode' => $mode,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Perform full rebuild from all transaction tables
     */
    private static function performFullRebuild(int $customerId, bool $updateDb): array
    {
        $stats = self::rebuildBalancesFromTransactions($customerId, null);
        if ($updateDb) {
            // Update last_verified_at for all monthly records
            CustomerBalanceMonthly::where('customer_id', $customerId)
                ->update(['last_verified_at' => now()]);
        }

        return [
            'current_balance' => $stats['final_balance'],
            'breakdown' => [
                'total_sales' => $stats['transactions_processed']['sales'] ?? 0,
                'total_returns' => $stats['transactions_processed']['returns'] ?? 0,
                'total_payments' => $stats['transactions_processed']['payments'] ?? 0,
                'total_credit_notes' => $stats['transactions_processed']['credit_notes'] ?? 0,
                'total_debit_notes' => $stats['transactions_processed']['debit_notes'] ?? 0,
            ],
            'months_rebuilt' => $stats['months_rebuilt'],
            'monthly_records' => $stats['monthly_records'],
        ];
    }

    /**
     * Perform incremental update based on months back
     */
    private static function performIncrementalUpdate(int $customerId, int $monthsBack, bool $updateDb): array
    {
        // Recalculate only the specified number of months back
        $stats = self::recalculateClosingBalances($customerId, $monthsBack);

        $customer = Customer::find($customerId);

        return [
            'current_balance' => $customer->current_balance,
            'months_processed' => $stats['months_processed'],
            'months_recalculated' => $stats['months_recalculated'],
        ];
    }

    /**
     * Get opening balance for a specific month
     * Checks: Previous month → Previous year closing → 0
     *
     * @param int $customerId
     * @param int $year
     * @param int $month
     * @return float
     */
    public static function getOpeningBalance(int $customerId, int $year, int $month): float
    {
        // If January, check previous year's closing balance
        if ($month === 1) {
            $previousYear = $year - 1;

            // Check if previous year closing exists in yearly balance table
            $yearlyBalance = CustomerBalanceYearly::where('customer_id', $customerId)
                ->where('year', $previousYear)
                ->first();

            if ($yearlyBalance) {
                return (float) $yearlyBalance->closing_balance;
            }

            // If no yearly balance, get last month of previous year from monthly
            $lastMonthOfPreviousYear = CustomerBalanceMonthly::where('customer_id', $customerId)
                ->where('year', $previousYear)
                ->where('month', 12)
                ->first();

            return $lastMonthOfPreviousYear ? (float) $lastMonthOfPreviousYear->closing_balance : 0.0;
        }

        // For other months, get previous month's closing balance
        $previousMonth = $month - 1;
        $previousMonthBalance = CustomerBalanceMonthly::where('customer_id', $customerId)
            ->where('year', $year)
            ->where('month', $previousMonth)
            ->first();

        return $previousMonthBalance ? (float) $previousMonthBalance->closing_balance : 0.0;
    }

    /**
     * Calculate yearly closing balance
     * Sums all monthly balances and creates yearly record
     *
     * @param int $customerId
     * @param int $year
     * @return array
     */
    public static function calculateYearlyClosing(int $customerId, int $year): array
    {
        $stats = [
            'customer_id' => $customerId,
            'year' => $year,
            'months_included' => 0,
            'yearly_balance' => 0,
        ];

        try {
            DB::transaction(function () use ($customerId, $year, &$stats) {
                // Get all monthly balances for the year
                $monthlyBalances = CustomerBalanceMonthly::where('customer_id', $customerId)
                    ->where('year', $year)
                    ->orderBy('month', 'asc')
                    ->get();

                if ($monthlyBalances->isEmpty()) {
                    Log::info("No monthly balances found for customer {$customerId} in year {$year}");
                    return;
                }

                $stats['months_included'] = $monthlyBalances->count();

                // Aggregate all transaction data
                $yearlyData = [
                    'total_sale' => $monthlyBalances->sum('total_sale'),
                    'total_sale_amount' => $monthlyBalances->sum('total_sale_amount'),
                    'total_return' => $monthlyBalances->sum('total_return'),
                    'total_return_amount' => $monthlyBalances->sum('total_return_amount'),
                    'total_credit' => $monthlyBalances->sum('total_credit'),
                    'total_credit_amount' => $monthlyBalances->sum('total_credit_amount'),
                    'total_debit' => $monthlyBalances->sum('total_debit'),
                    'total_debit_amount' => $monthlyBalances->sum('total_debit_amount'),
                    'total_payment' => $monthlyBalances->sum('total_payment'),
                    'total_payment_amount' => $monthlyBalances->sum('total_payment_amount'),
                ];

                // Calculate transaction total for the year using correct formula
                $yearlyData['transaction_total'] =
                    ($yearlyData['total_payment_amount'] + $yearlyData['total_credit_amount'] + $yearlyData['total_return_amount']) -
                    ($yearlyData['total_sale_amount'] + $yearlyData['total_debit_amount']);

                // Get the closing balance from the last month of the year
                $lastMonth = $monthlyBalances->last();
                $yearlyData['closing_balance'] = $lastMonth->closing_balance;

                // Create or update yearly balance record
                CustomerBalanceYearly::updateOrCreate(
                    [
                        'customer_id' => $customerId,
                        'year' => $year,
                    ],
                    $yearlyData
                );

                $stats['yearly_balance'] = $yearlyData['closing_balance'];

                // Mark all monthly records as closed for the year
                CustomerBalanceMonthly::where('customer_id', $customerId)
                    ->where('year', $year)
                    ->update(['last_verified_at' => now()]);
            });

        } catch (\Exception $e) {
            Log::error("Failed to calculate yearly closing balance for customer {$customerId}, year {$year}: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Daily refresh of all customer balances (incremental - last 1 month)
     * This runs daily at midnight to keep balances up to date
     *
     * @return array
     */
    public static function refreshAllCustomerBalancesDaily(): array
    {
        $stats = [
            'total' => 0,
            'processed' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            Customer::select('id')->chunk(500, function ($customers) use (&$stats) {
                foreach ($customers as $customer) {
                    $stats['total']++;

                    try {
                        // Incremental refresh - recalculate last 1 month
                        self::calculateCustomerBalance(
                            $customer->id,
                            'incremental',
                            ['months_back' => 1]
                        );
                        $stats['processed']++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $stats['error_details'][] = [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to refresh daily balance for customer {$customer->id}: " . $e->getMessage());
                    }
                }
            });

            // Only log if there were errors or actual updates
            if ($stats['errors'] > 0) {
                Log::warning("Daily balance refresh completed with errors", [
                    'total' => $stats['total'],
                    'processed' => $stats['processed'],
                    'errors' => $stats['errors']
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Daily customer balance refresh failed: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Calculate yearly closing for all customers
     *
     * @param int $year
     * @return array
     */
    public static function calculateYearlyClosingForAllCustomers(int $year): array
    {
        $stats = [
            'year' => $year,
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            Customer::select('id')->chunk(500, function ($customers) use (&$stats, $year) {
                foreach ($customers as $customer) {
                    $stats['total']++;

                    try {
                        $result = self::calculateYearlyClosing($customer->id, $year);

                        if ($result['months_included'] > 0) {
                            $stats['processed']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $stats['error_details'][] = [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to calculate yearly closing for customer {$customer->id}: " . $e->getMessage());
                    }
                }
            });

            // Only log summary with errors if any
            if ($stats['errors'] > 0) {
                Log::warning("Yearly closing for {$year} completed with errors", [
                    'processed' => $stats['processed'],
                    'errors' => $stats['errors']
                ]);
            } else {
                Log::info("Yearly closing for {$year} completed", [
                    'processed' => $stats['processed']
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Yearly closing balance calculation failed for year {$year}: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

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

        } catch (\Exception $e) {
            Log::error("Failed to update monthly balance: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Rebuild customer balances from actual transaction tables
     * This is the TRUE refresh that queries all transactions and rebuilds everything
     *
     * @param int $customerId
     * @param int|null $monthsBack - Number of months to rebuild (null = all time)
     * @return array - Statistics about the operation
     */
    public static function rebuildBalancesFromTransactions(int $customerId, ?int $monthsBack = null): array
    {
        $stats = [
            'customer_id' => $customerId,
            'transactions_processed' => [
                'sales' => 0,
                'returns' => 0,
                'payments' => 0,
                'credit_notes' => 0,
                'debit_notes' => 0,
            ],
            'months_rebuilt' => 0,
            'monthly_records' => [],
            'final_balance' => 0,
        ];

        try {
            DB::transaction(function () use ($customerId, $monthsBack, &$stats) {

                // Step 1: Fetch and group all transactions by month
                $monthlyData = self::fetchAndGroupTransactions($customerId, $monthsBack, $stats);
                // Step 2: Rebuild monthly balance records
                foreach ($monthlyData as $yearMonth => $data) {
                    [$year, $month] = explode('-', $yearMonth);

                    // Update or create monthly balance record
                    $monthlyBalance = CustomerBalanceMonthly::updateOrCreate(
                        [
                            'customer_id' => $customerId,
                            'year' => (int) $year,
                            'month' => (int) $month,
                        ],
                        [
                            'total_sale' => $data['sale_count'],
                            'total_sale_amount' => $data['sale_amount'],
                            'total_return' => $data['return_count'],
                            'total_return_amount' => $data['return_amount'],
                            'total_credit' => $data['credit_count'],
                            'total_credit_amount' => $data['credit_amount'],
                            'total_debit' => $data['debit_count'],
                            'total_debit_amount' => $data['debit_amount'],
                            'total_payment' => $data['payment_count'],
                            'total_payment_amount' => $data['payment_amount'],
                            'transaction_total' => $data['transaction_total'],
                            'closing_balance' => 0, // Will be calculated in next step
                            'last_updated_by' => null,
                            'updated_by_entry_id' => 0,
                        ]
                    );

                    $stats['months_rebuilt']++;
                    $stats['monthly_records'][] = [
                        'year' => $year,
                        'month' => $month,
                        'transaction_total' => $monthlyBalance->transaction_total,
                    ];
                }

                // Step 3: Calculate closing balances sequentially (oldest to newest)
                $monthlyBalances = CustomerBalanceMonthly::where('customer_id', $customerId)
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();

                $runningBalance = 0;
                foreach ($monthlyBalances as $balance) {
                    $runningBalance += $balance->transaction_total;
                    $balance->closing_balance = $runningBalance;
                    $balance->save();
                }

                // Step 4: Update customer's current balance
                $customer = Customer::find($customerId);
                $customer->current_balance = $runningBalance;
                $customer->save();

                $stats['final_balance'] = $runningBalance;
            });

        } catch (\Exception $e) {
            Log::error("Failed to rebuild balances from transactions for customer {$customerId}: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Fetch and group all transactions by month - SIMPLIFIED VERSION
     * Formula: Balance = (Sales + Debit Notes) - (Payments + Returns + Credit Notes)
     *
     * @param int $customerId
     * @param int|null $monthsBack
     * @param array $stats
     * @return array
     */
    private static function fetchAndGroupTransactions(int $customerId, ?int $monthsBack, array &$stats): array
    {
        $fromDate = $monthsBack ? now()->subMonths($monthsBack)->startOfMonth() : null;
        $grouped = [];
     
        // 1. Fetch Sales (DEBIT - customer owes us)
        $salesQuery = Sale::where('customer_id', $customerId)->approved();
        if ($fromDate) $salesQuery->where('date', '>=', $fromDate);

        $sales = $salesQuery->select('id', 'date', 'total_usd')->get();
        $stats['transactions_processed']['sales'] = $sales->count();
    
        foreach ($sales as $sale) {
            $key = $sale->date->year . '-' . $sale->date->month;
            if (!isset($grouped[$key])) {
                $grouped[$key] = self::initMonthData();
            }
            $grouped[$key]['sale_count']++;
            $grouped[$key]['sale_amount'] += $sale->total_usd;
        }
        // 2. Fetch Returns (CREDIT - reduces customer debt)
        $returnsQuery = CustomerReturn::where('customer_id', $customerId)->approved()->received();
        if ($fromDate) $returnsQuery->where('date', '>=', $fromDate);

        $returns = $returnsQuery->select('id', 'date', 'total_usd')->get();
        $stats['transactions_processed']['returns'] = $returns->count();

        foreach ($returns as $return) {
            $key = $return->date->year . '-' . $return->date->month;
            if (!isset($grouped[$key])) {
                $grouped[$key] = self::initMonthData();
            }
            $grouped[$key]['return_count']++;
            $grouped[$key]['return_amount'] += $return->total_usd;
        }

        // 3. Fetch Payments (CREDIT - reduces customer debt)
        $paymentsQuery = CustomerPayment::where('customer_id', $customerId)->approved();
        if ($fromDate) $paymentsQuery->where('date', '>=', $fromDate);
         
        $payments = $paymentsQuery->select('id', 'date', 'amount_usd')->get();
        $stats['transactions_processed']['payments'] = $payments->count();

        foreach ($payments as $payment) {
            $key = $payment->date->year . '-' . $payment->date->month;
            if (!isset($grouped[$key])) {
                $grouped[$key] = self::initMonthData();
            }
            $grouped[$key]['payment_count']++;
            $grouped[$key]['payment_amount'] += $payment->amount_usd;
        }

        // 4. Fetch Credit/Debit Notes
        $notesQuery = CustomerCreditDebitNote::where('customer_id', $customerId);
        if ($fromDate) $notesQuery->where('date', '>=', $fromDate);

        $notes = $notesQuery->select('id', 'date', 'type', 'amount_usd')->get();

        foreach ($notes as $note) {
            $key = $note->date->year . '-' . $note->date->month;
            if (!isset($grouped[$key])) {
                $grouped[$key] = self::initMonthData();
            }

            if ($note->type === 'credit') {
                $stats['transactions_processed']['credit_notes']++;
                $grouped[$key]['credit_count']++;
                $grouped[$key]['credit_amount'] += $note->amount_usd;
            } else {
                $stats['transactions_processed']['debit_notes']++;
                $grouped[$key]['debit_count']++;
                $grouped[$key]['debit_amount'] += $note->amount_usd;
            }
        }

        // Calculate transaction_total for each month
        // Formula: (Payments + Credit Notes + Sale Returns) - (Sales + Debit Notes)
        foreach ($grouped as $key => &$data) {
            $data['transaction_total'] =
                ($data['payment_amount'] + $data['credit_amount'] + $data['return_amount']) -
                ($data['sale_amount'] + $data['debit_amount']);
        }
        ksort($grouped);
        return $grouped;
    }

    /**
     * Initialize empty month data structure
     */
    private static function initMonthData(): array
    {
        return [
            'sale_count' => 0,
            'sale_amount' => 0,
            'return_count' => 0,
            'return_amount' => 0,
            'credit_count' => 0,
            'credit_amount' => 0,
            'debit_count' => 0,
            'debit_amount' => 0,
            'payment_count' => 0,
            'payment_amount' => 0,
            'transaction_total' => 0,
        ];
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

    /**
     * Recalculate closing balances for specified number of months back
     * This will recalculate all months from X months ago to current month sequentially
     *
     * @param int $customerId
     * @param int $monthsBack - Number of months to go back (default: 3)
     * @return array - Statistics about the operation
     */
    public static function recalculateClosingBalances(int $customerId, int $monthsBack = 3): array
    {
        $stats = [
            'customer_id' => $customerId,
            'months_processed' => 0,
            'months_skipped' => 0,
            'months_recalculated' => [],
            'errors' => []
        ];

        try {
            DB::transaction(function () use ($customerId, $monthsBack, &$stats) {
                // Calculate the date range
                $endDate = now();
                $startDate = now()->subMonths($monthsBack);

                // Get all monthly balance records within the date range, ordered from oldest to newest
                $monthlyBalances = CustomerBalanceMonthly::where('customer_id', $customerId)
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->where('year', '>', $startDate->year)
                            ->orWhere(function ($q) use ($startDate) {
                                $q->where('year', $startDate->year)
                                    ->where('month', '>=', $startDate->month);
                            });
                    })
                    ->where(function ($query) use ($endDate) {
                        $query->where('year', '<', $endDate->year)
                            ->orWhere(function ($q) use ($endDate) {
                                $q->where('year', $endDate->year)
                                    ->where('month', '<=', $endDate->month);
                            });
                    })
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();

                if ($monthlyBalances->isEmpty()) {
                    return;
                }

                // For each month, recalculate the closing balance
                foreach ($monthlyBalances as $balance) {
                    try {
                        // Get the closing balance from the previous month
                        $previousMonthBalance = CustomerBalanceMonthly::where('customer_id', $customerId)
                            ->where('closing_balance', '>', 0)
                            ->where(function ($query) use ($balance) {
                                $query->where('year', '<', $balance->year)
                                    ->orWhere(function ($q) use ($balance) {
                                        $q->where('year', $balance->year)
                                            ->where('month', '<', $balance->month);
                                    });
                            })
                            ->orderBy('year', 'desc')
                            ->orderBy('month', 'desc')
                            ->value('closing_balance') ?? 0.0;

                        // Recalculate transaction total
                        self::calculateBalance($balance);

                        // Update closing balance
                        $balance->closing_balance = $previousMonthBalance + $balance->transaction_total;
                        $balance->save();

                        $stats['months_processed']++;
                        $stats['months_recalculated'][] = [
                            'year' => $balance->year,
                            'month' => $balance->month,
                            'transaction_total' => $balance->transaction_total,
                            'closing_balance' => $balance->closing_balance
                        ];

                    } catch (\Exception $e) {
                        $stats['errors'][] = [
                            'year' => $balance->year,
                            'month' => $balance->month,
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to recalculate month {$balance->year}-{$balance->month} for customer {$customerId}: " . $e->getMessage());
                    }
                }

                // Update customer's current balance with the latest closing balance
                self::updateCustomerCurrentBalance($customerId);
            });

        } catch (\Exception $e) {
            Log::error("Failed to recalculate closing balances for customer {$customerId}: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Process end of month calculation for all customers with optional months back parameter
     * This should be run at the end of each month to calculate closing balances
     *
     * @param int|null $monthsBack - Number of months to recalculate (null for current month only)
     * @return array - Statistics about the operation
     */
    public static function processMonthlyClosingForAllCustomers(?int $monthsBack = null): array
    {
        $stats = [
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            // Process 500 customers at a time to balance memory usage and performance
            Customer::select('id')->chunk(500, function ($customers) use (&$stats, $monthsBack) {
                foreach ($customers as $customer) {
                    $stats['total']++;

                    try {
                        if ($monthsBack !== null && $monthsBack > 0) {
                            // Recalculate multiple months back
                            self::recalculateClosingBalances($customer->id, $monthsBack);
                        } else {
                            // Only calculate current month
                            self::endOfMonthCalculation($customer->id);
                        }
                        $stats['processed']++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $stats['error_details'][] = [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to process end of month calculation for customer {$customer->id}: " . $e->getMessage());
                    }
                }
            });

            // Only log if there were errors
            if ($stats['errors'] > 0) {
                Log::warning("Monthly closing completed with errors", [
                    'processed' => $stats['processed'],
                    'errors' => $stats['errors']
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Monthly closing balance calculation failed: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    // this will be use in scheduler every end of the month
    public static function endOfMonthCalculation(int $customerId): void
    {
        // here we first fetch last closing balance,
        $previousMonthBalance = self::latestClosingBalance($customerId);

        // fetch all amount from different transaction table
        $balance = CustomerBalanceMonthly::where('customer_id', $customerId)
            ->where('closing_balance' , 0)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        // If no current month balance exists, skip this customer
        if (!$balance) {
            return;
        }

        // sum it to transaction_total
        self::calculateBalance($balance);

        // update closing_balance = transaction_total + latest closing balance
        $balance->closing_balance = $previousMonthBalance + $balance->transaction_total;

        $balance->save();

        $customer = Customer::find($customerId);
        $customer->current_balance = $balance->closing_balance;
        $customer->save();
    }

    /**
     * Calculate transaction total for a monthly balance record
     * Formula: (Payments + Credit Notes + Sale Returns) - (Sales + Debit Notes)
     * Positive = We owe customer (credit), Negative = Customer owes us (debit)
     */
    private static function calculateBalance(CustomerBalanceMonthly $balance): void
    {
        $balance->transaction_total =
            ($balance->total_payment_amount + $balance->total_credit_amount + $balance->total_return_amount) -
            ($balance->total_sale_amount + $balance->total_debit_amount);
    }

}