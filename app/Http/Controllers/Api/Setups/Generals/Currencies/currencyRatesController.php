<?php

namespace App\Http\Controllers\Api\Setups\Generals\Currencies;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Setups\Generals\Currencies\currencyRateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Generals\Currencies\currencyRate;
use App\Traits\HasBooleanFilters;
use App\Traits\HasPagination;
use Illuminate\Http\Request;

class currencyRatesController extends Controller
{
    use HasPagination, HasBooleanFilters;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // this will show rate table we will click on change, then i will become a changesable form
        $query = currencyRate::query()
            ->with('currency')
            ->searchable($request)
            ->sortable($request)
            // ->active()
            ;

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rates = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Currencies chage rates retrieved successfully',
            $rates
        );
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

            // Format rates to avoid scientific notation
            $currentRateFormatted = $currentActiveRate ? number_format($currentActiveRate->rate, 11, '.', '') : null;
            $newRateFormatted = number_format((float)$newRate, 11, '.', '');

            // If there's no current rate, or the rate is different, create a new one
            if (!$currentActiveRate || bccomp($currentRateFormatted, $newRateFormatted, 11) !== 0) {
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
