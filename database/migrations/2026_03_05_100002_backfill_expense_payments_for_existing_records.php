<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * For all existing expense transactions:
     *  - Set paid_amount = amount, paid_amount_usd = amount_usd  (all were paid immediately at creation)
     *  - Create one ExpensePayment record per transaction mirroring the original payment
     *    (raw DB to avoid model events and double-deducting account balances)
     */
    public function up(): void
    {
        $now = now();

        $transactions = DB::table('expense_transactions')
            ->whereNull('deleted_at')
            ->whereNotNull('account_id')
            ->get(['id', 'amount', 'amount_usd', 'currency_id', 'currency_rate', 'date', 'account_id', 'created_by', 'order_number', 'check_number', 'bank_ref_number']);

        foreach ($transactions as $tx) {
            DB::table('expense_transactions')
                ->where('id', $tx->id)
                ->update([
                    'paid_amount'     => $tx->amount,
                    'paid_amount_usd' => $tx->amount_usd ?? 0,
                ]);

            DB::table('expense_payments')->insert([
                'expense_transaction_id' => $tx->id,
                'account_id'             => $tx->account_id,
                'amount'                 => $tx->amount,
                'amount_usd'             => $tx->amount_usd,
                'currency_id'            => $tx->currency_id,
                'currency_rate'          => $tx->currency_rate,
                'date'                   => $tx->date,
                'prefix'                 => 'EP',
                'note'                   => 'Migrated from original expense transaction',
                'order_number'           => $tx->order_number,
                'check_number'           => $tx->check_number,
                'bank_ref_number'        => $tx->bank_ref_number,
                'created_by'             => $tx->created_by,
                'updated_by'             => $tx->created_by,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('expense_payments')
            ->where('note', 'Migrated from original expense transaction')
            ->delete();

        DB::table('expense_transactions')->update(['paid_amount' => 0, 'paid_amount_usd' => 0]);
    }
};
