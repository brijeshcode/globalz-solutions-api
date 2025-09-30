<?php 

namespace App\Helpers;

use App\Models\Employees\Employee;
use Illuminate\Support\Facades\Auth;

class ApiHelper {
    
    public static function authUser()
    {
        /** @var \App\Models\User $user */

        $user = Auth::user();

        return $user;
    }

    public static function isSalesman(): bool 
    {
        return self::authUser()->isSalesman();
    }

    public static function salesmanEmployee(): Employee | null
    {
        $user = self::authUser();
        return $user->isSalesman() ? Employee::where('user_id', $user->id )->first(): null;
    }

    public static function toUsd($amount, $rate): float
    {
        // Formula: Amount × (USD per 1 unit of your currency)
        return $amount * $rate;
    }
}