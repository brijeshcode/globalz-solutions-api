<?php 

namespace App\Helpers;

use App\Models\Setups\TaxCode;

class CommonHelper {
    
    public static function getDefaultTax(): ?TaxCode
    {
        return TaxCode::default()->first();
    }

    public static function getTaxLable(): string
    {
        $defaultTax = self::getDefaultTax();
        if(is_null($defaultTax)){
            return '';
        }
        $taxPercent = (float) $defaultTax->tax_percent;
        $label = $defaultTax->name .' '. ($taxPercent == (int) $taxPercent ? (int) $taxPercent : $taxPercent) . '%';
        return $label;
    }
    
    public static function invoiceNb2(): string 
    {
        return  'ملاحظة : ألضريبة على ألقيمة المضافة لا تسترد بعد ثلاثة أشهر من تاريخ إصدار ألفاتورة';
    }

    public static function invoiceNb1(): string 
    {
        return 'Payment in USD or Market Price.';
    }
}