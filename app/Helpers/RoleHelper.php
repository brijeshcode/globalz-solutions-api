<?php 

namespace App\Helpers;

use App\Models\Employees\Employee;
use Illuminate\Support\Facades\Auth;

class RoleHelper {
    
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

    public static function isWarehouseManager(): bool 
    {
        return self::authUser()->isWarehouseManager();
    }

    public static function isAdmin(): bool 
    {
        return self::authUser()->isAdmin();
    }

    public static function isDeveloper(): bool 
    {
        return self::authUser()->isDeveloper();
    }

    public static function isSuperAdmin(): bool 
    {
        return self::authUser()->isSuperAdmin();
    }

    public static function getSalesmanEmployee(): Employee | null
    {
        $user = self::authUser();
        return $user->isSalesman() ? Employee::where('user_id', $user->id )->first(): null;
    }
}