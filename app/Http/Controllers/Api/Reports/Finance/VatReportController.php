<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customers\CustomerReturn;
use Illuminate\Http\Request;
use App\Models\Customers\Sale;

class VatReportController extends Controller
{
    //
    /**
     * report contains : 
     * + total inv sales
     * - total inv returns 
     * - vat return on inv returns
     * + vat on this inv sales
     * - vat paid vai expense vat
     * - vat paid vai purchase
     * - vat paid on expense
     * 
     * in end we will get vat difference 
     *  
     * 
     */

    public function index()
    {
        $invSale = Sale::where('prefix', Sale::TAXPREFIX)->get();
        $returnSale = CustomerReturn::where('prefix', CustomerReturn::TAXPREFIX)->get();

        
        
    }
    
}
