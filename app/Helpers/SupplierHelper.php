<?php 

namespace App\Helpers;

use App\Models\Setups\Supplier;

class SuppliersHelper {
    
    public static function addBalance( Supplier $supplier, float $balance): void
    {
        $supplier->current_balance += $balance;
        $supplier->save();
    }

    public static function removeBalance( Supplier $supplier, float $balance): void
    {
        $supplier->current_balance -= $balance;
        $supplier->save();
    }
 
}