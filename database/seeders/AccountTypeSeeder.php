<?php

namespace Database\Seeders;

use App\Models\Setups\Accounts\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountTypes = [
            [
                'name' => 'Cash',
                'description' => 'Cash account type',
                'is_active' => true,
            ],
            [
                'name' => 'Bank',
                'description' => 'Bank account type',
                'is_active' => true,
            ],
            [
                'name' => 'Others',
                'description' => 'Other account types',
                'is_active' => true,
            ],
        ];

        foreach ($accountTypes as $accountType) {
            AccountType::create($accountType);
        }
    }
}