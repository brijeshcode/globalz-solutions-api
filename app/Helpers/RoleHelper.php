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
        // Only super admin role can access super admin content
        $user = self::authUser();
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    public static function isAdmin(): bool
    {
        $user = self::authUser();
        if (!$user) {
            return false;
        }
        return $user->isAdmin();
    }

    public static function isWarehouseManager(): bool
    {
        $user = self::authUser();
        if (!$user) {
            return false;
        }
        return $user->isWarehouseManager();
    }

    public static function isSalesman(): bool
    {
        $user = self::authUser();
        if (!$user) {
            return false;
        }
        return $user->isSalesman();
    }

    // --- Hierarchical Access Check Methods (New and Shortened Logic) ---
    public static function canDeveloper(): bool
    {
        return self::isDeveloper();
    }

    public static function canSuperAdmin(): bool
    {
        return self::isDeveloper() || self::isSuperAdmin();
    }

    public static function canAdmin(): bool
    {
        return self::canSuperAdmin() || self::isAdmin();
    }

    public static function canWarehouseManager(): bool
    {
        return self::canAdmin() || self::isWarehouseManager();
    }

    public static function canSalesman(): bool
    {
        return self::canAdmin() || self::isSalesman();
    }

    // --- Employee Retrieval Methods (Unchanged) ---
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
        if (!$user) {
            return null;
        }
        return $user->isWarehouseManager() ? Employee::where('user_id', $user->id )->first(): null;
    }
}