<?php 

namespace App\Helpers;

use App\Models\Accounts\Account;

class AccountsHelper {
    
    public static function addBalance( Account $account, float $balance): void
    {
        $account->current_balance += $balance;
        $account->save();
    }

    public static function removeBalance( Account $account, float $balance): void
    {
        $account->current_balance -= $balance;
        $account->save();
    }
 
}