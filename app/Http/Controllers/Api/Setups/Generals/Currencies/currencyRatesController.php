<?php

namespace App\Http\Controllers\Api\Setups\Generals\Currencies;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Setups\Generals\Currencies\currencyRateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Generals\Currencies\currencyRate;
use Illuminate\Http\Request;

class currencyRatesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // this will show rate table we will click on change, then i will become a changesable form
        $rates = currencyRate::with('currency')->active()->get();
        return ApiResponse::index('Currencies rates', $rates, currencyRateResource::class);
    }
     
    public function changeRate(Request $request)
    {
        $request->validate([
            'rates' => 'required|array',
            'rates.*.currency_id' => 'required|exists:currencies,id',
            'rates.*.rate' => 'required|numeric|min:0',
        ]);

        $updatedRates = [];
        $unchangedRates = [];

        foreach ($request->rates as $rateData) {
            $currencyId = $rateData['currency_id'];
            $newRate = $rateData['rate'];
            
            // Get the current active rate for this currency
            $currentActiveRate = currencyRate::where('currency_id', $currencyId)
                ->where('is_active', true)
                ->first();
            
            // If there's no current rate, or the rate is different, create a new one
            if (!$currentActiveRate || bccomp((string)$currentActiveRate->rate, (string)$newRate, 6) !== 0) {
                // Deactivate all existing rates for this currency
                currencyRate::where('currency_id', $currencyId)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                
                // Create new active rate
                $newRateRecord = currencyRate::create([
                    'currency_id' => $currencyId,
                    'rate' => $newRate,
                    'is_active' => true,
                ]);
                
                $updatedRates[] = $newRateRecord;
            } else {
                $unchangedRates[] = $currentActiveRate;
            }
        }

        $totalUpdated = count($updatedRates);
        $totalUnchanged = count($unchangedRates);

        return ApiResponse::update(
            "Currency rates updated successfully. {$totalUpdated} rates updated, {$totalUnchanged} rates unchanged.",
            [
                'updated_rates' => $updatedRates,
                'unchanged_rates' => $unchangedRates,
                'summary' => [
                    'total_updated' => $totalUpdated,
                    'total_unchanged' => $totalUnchanged,
                    'total_processed' => $totalUpdated + $totalUnchanged
                ]
            ]
        );
    }

}
