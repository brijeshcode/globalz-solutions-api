<?php

namespace Database\Seeders;

use App\Models\Accounts\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $accounts = [
            [
                'name' => 'USD Cash',
                'account_type_id' => 1,
                'currency_id' => 1,
                'opening_balance' => 0,
                'description' => 'Cash usd account',
                'is_active' => true,
            ],
            [
                'name' => 'EURO cash',
                'account_type_id' => 1,
                'currency_id' => 2,
                'opening_balance' => 0,
                'description' => 'Cash euro account',
                'is_active' => true,
            ],
            [
                'name' => 'LBP bank',
                'account_type_id' => 2,
                'currency_id' => 4,
                'opening_balance' => 0,
                'description' => 'Bank euro account',
                'is_active' => true,
            ],
             
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
