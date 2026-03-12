<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Models\Landlord\TenantFeature;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Generals\Currencies\currencyRate;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantCurrencyController extends Controller
{
    /**
     * Default USD currency data used for auto-seeding.
     */
    public static function usdDefaults(): array
    {
        return [
            'name'               => 'US Dollar',
            'code'               => 'USD',
            'symbol'             => '$',
            'symbol_position'    => 'before',
            'decimal_places'     => 2,
            'decimal_separator'  => '.',
            'thousand_separator' => ',',
            'calculation_type'   => 'multiply',
            'is_active'          => true,
        ];
    }

    /**
     * Create the USD currency if it does not already exist.
     * Safe to call multiple times (idempotent).
     */
    public static function seedUsdIfMissing(): void
    {
        if (!Currency::where('code', 'USD')->exists()) {
            Currency::create(array_merge(self::usdDefaults(), [
                'created_by' => 1,
                'updated_by' => 1,
            ]));
        }
    }

    /**
     * List all currencies for a tenant.
     * Auto-seeds USD if it is not yet present.
     *
     * GET /tenants/{tenant}/currencies
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $currencies = $tenant->execute(function () {
            self::seedUsdIfMissing();

            return Currency::with('activeRate:id,currency_id,rate')->orderBy('name')->get();
        });

        return ApiResponse::index('Currencies retrieved successfully', $currencies);
    }

    /**
     * Create a new currency for a tenant.
     *
     * POST /tenants/{tenant}/currencies
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:100',
            'code'               => 'required|string|size:3',
            'symbol'             => 'required|string|max:10',
            'symbol_position'    => 'required|string|in:before,after',
            'decimal_places'     => 'required|integer|min:0|max:8',
            'decimal_separator'  => 'required|string|max:1',
            'thousand_separator' => 'required|string|max:1',
            'calculation_type'   => ['required', 'string', Rule::in(Currency::CALCULATION_TYPE)],
            'is_active'          => 'boolean',
        ]);

        try {
            $currency = $tenant->execute(function () use ($validated) {
                // In single-currency mode only 2 currencies are allowed: USD + local currency
                if (!TenantFeature::isEnabled('multi_currency') && Currency::count() >= 2) {
                    throw new \InvalidArgumentException(
                        'This tenant is in single-currency mode. Only 2 currencies are allowed (USD + local currency). No additional currencies can be added.'
                    );
                }

                // Ensure code is unique within this tenant
                if (Currency::where('code', strtoupper($validated['code']))->exists()) {
                    throw new \InvalidArgumentException("Currency code '{$validated['code']}' already exists for this tenant.");
                }

                return Currency::create([
                    'name'               => $validated['name'],
                    'code'               => strtoupper($validated['code']),
                    'symbol'             => $validated['symbol'],
                    'symbol_position'    => $validated['symbol_position'],
                    'decimal_places'     => $validated['decimal_places'],
                    'decimal_separator'  => $validated['decimal_separator'],
                    'thousand_separator' => $validated['thousand_separator'],
                    'calculation_type'   => $validated['calculation_type'],
                    'is_active'          => $validated['is_active'] ?? true,
                    'created_by'         => 1,
                    'updated_by'         => 1,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::failValidation(['currency' => $e->getMessage()]);
        }

        return ApiResponse::store('Currency created successfully', [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'currency'    => $currency,
        ]);
    }

    /**
     * List active currency rates for a tenant.
     *
     * GET /tenants/{tenant}/currency-rates
     */
    public function indexRates(Tenant $tenant): JsonResponse
    {
        $rates = $tenant->execute(function () {
            return currencyRate::with('currency:id,name,code,symbol')
                ->active()
                ->get();
        });

        return ApiResponse::index('Currency rates retrieved successfully', $rates);
    }

    /**
     * Update currency rates for a tenant.
     *
     * POST /tenants/{tenant}/currency-rates
     */
    public function changeRate(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'rates'                => 'required|array',
            'rates.*.currency_id'  => 'required|integer',
            'rates.*.rate'         => 'required|numeric|min:0',
        ]);

        $updatedRates   = [];
        $unchangedRates = [];

        $tenant->execute(function () use ($request, &$updatedRates, &$unchangedRates) {
            foreach ($request->rates as $rateData) {
                $currencyId = $rateData['currency_id'];
                $newRate    = $rateData['rate'];

                $currentActiveRate    = currencyRate::where('currency_id', $currencyId)->where('is_active', true)->first();
                $currentRateFormatted = $currentActiveRate ? number_format($currentActiveRate->rate, 11, '.', '') : null;
                $newRateFormatted     = number_format((float) $newRate, 11, '.', '');

                if (!$currentActiveRate || bccomp($currentRateFormatted, $newRateFormatted, 11) !== 0) {
                    currencyRate::where('currency_id', $currencyId)->where('is_active', true)->update(['is_active' => false]);

                    $updatedRates[] = currencyRate::create([
                        'currency_id' => $currencyId,
                        'rate'        => $newRate,
                        'is_active'   => true,
                    ]);
                } else {
                    $unchangedRates[] = $currentActiveRate;
                }
            }

            if (!empty($updatedRates)) {
                AttachCacheVersion::invalidate('currency_rate');
                AttachCacheVersion::invalidate('currencies');
            }
        });

        $totalUpdated   = count($updatedRates);
        $totalUnchanged = count($unchangedRates);

        return ApiResponse::update(
            "{$totalUpdated} rates updated, {$totalUnchanged} rates unchanged.",
            [
                'updated_rates'   => $updatedRates,
                'unchanged_rates' => $unchangedRates,
                'summary'         => [
                    'total_updated'   => $totalUpdated,
                    'total_unchanged' => $totalUnchanged,
                    'total_processed' => $totalUpdated + $totalUnchanged,
                ],
            ]
        );
    }

    /**
     * Update an existing currency for a tenant.
     *
     * PUT /tenants/{tenant}/currencies/{code}
     *
     * {code} is the ISO currency code (e.g. LBP, USD) — avoids ID guessing across tenants.
     */
    public function update(Request $request, Tenant $tenant, string $code): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:100',
            'symbol'             => 'sometimes|required|string|max:10',
            'symbol_position'    => 'sometimes|required|string|in:before,after',
            'decimal_places'     => 'sometimes|required|integer|min:0|max:8',
            'decimal_separator'  => 'sometimes|required|string|max:1',
            'thousand_separator' => 'sometimes|required|string|max:1',
            'calculation_type'   => ['sometimes', 'required', 'string', Rule::in(Currency::CALCULATION_TYPE)],
            'is_active'          => 'boolean',
        ]);

        $currency = $tenant->execute(function () use ($validated, $code) {
            $currency = Currency::where('code', strtoupper($code))->first();

            if (!$currency) {
                throw new \InvalidArgumentException("Currency '{$code}' not found for this tenant.");
            }

            $currency->update(array_merge($validated, ['updated_by' => 1]));

            return $currency->fresh();
        });

        return ApiResponse::update('Currency updated successfully', [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'currency'    => $currency,
        ]);
    }
}
