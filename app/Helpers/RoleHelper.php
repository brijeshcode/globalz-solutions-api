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

    public static function isDeveloper(): bool
    {
        // Only developer role can access developer content
        $user = self::authUser();
        if (!$user) {
            return false;
        }
        return $user->isDeveloper();
    }

    public static function isSuperAdmin(): bool
    {
        // // Developer has all access including super admin content
        // if (self::isDeveloper()) {
        //     return true;
        // }

        // Only super admin role can access super admin content
        return self::authUser()->isSuperAdmin();
    }

    public static function isAdmin(): bool
    {
        // // Super Admin can access admin content (this also includes Developer)
        // if (self::isSuperAdmin()) {
        //     return true;
        // }

        $user = self::authUser();
        if (!$user) {
            return false;
        }
        return $user->isAdmin();
    }

    public static function isWarehouseManager(): bool
    {
        // // Admin can access warehouse manager content (this also includes Super Admin and Developer)
        // if (self::isAdmin()) {
        //     return true;
        // }

        return self::authUser()->isWarehouseManager();
    }

    public static function isSalesman(): bool
    {
        $user = self::authUser();
        if (!$user) {
            return false;
        }

        return $user->isSalesman();
    }

    public static function getSalesmanEmployee(): Employee | null
    {
        $user = self::authUser();
        if (!$user) {
            return null;
        }

        return $user->isSalesman() ? Employee::where('user_id', $user->id)->first() : null;
    }

    public static function getWarehouseEmployee(): Employee | null
    {
        $user = self::authUser();
        return $user->isWarehouseManager() ? Employee::where('user_id', $user->id )->first(): null;
    }
}