<?php

namespace App\Http\Controllers\Api\Setups\Generals\Currencies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\CurrenciesStoreRequest;
use App\Http\Requests\Api\Setups\CurrenciesUpdateRequest;
use App\Http\Resources\Api\Setups\CurrencyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Currency;
use App\Traits\HasBooleanFilters;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrenciesController extends Controller
{
    use HasPagination, HasBooleanFilters;

    public function index(Request $request): JsonResponse
    {
        $query = Currency::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);
            ;
        
        $this->applyBooleanFilter($query, 'is_active', $request->input('is_active'));

        $currencies = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Currencies retrieved successfully',
            $currencies,
            CurrencyResource::class
        );
    }

    public function store(CurrenciesStoreRequest $request): JsonResponse
    {
        $currency = Currency::create($request->validated());
        $currency->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Currency created successfully',
            new CurrencyResource($currency)
        );
    }

    public function show(Currency $currency): JsonResponse
    {
        $currency->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Currency retrieved successfully',
            new CurrencyResource($currency)
        );
    }

    public function update(CurrenciesUpdateRequest $request, Currency $currency): JsonResponse
    {
        $currency->update($request->validated());
        $currency->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Currency updated successfully',
            new CurrencyResource($currency)
        );
    }

    public function destroy(Currency $currency): JsonResponse
    {
        $currency->delete();

        return ApiResponse::delete('Currency deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Currency::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);
            
        $this->applyBooleanFilter($query, 'is_active', $request->input('is_active'));


        $currencies = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed currencies retrieved successfully',
            $currencies,
            CurrencyResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);
        $currency->restore();
        $currency->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Currency restored successfully',
            new CurrencyResource($currency)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $currency = Currency::onlyTrashed()->findOrFail($id);
        $currency->forceDelete();

        return ApiResponse::delete('Currency permanently deleted successfully');
    }
}