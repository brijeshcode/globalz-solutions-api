<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\CountriesStoreRequest;
use App\Http\Requests\Api\Setups\CountriesUpdateRequest;
use App\Http\Resources\Api\Setups\CountryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Country;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountriesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Country::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        $countries = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Countries retrieved successfully',
            $countries,
            CountryResource::class
        );
    }

    public function store(CountriesStoreRequest $request): JsonResponse
    {
        $country = Country::create($request->validated());
        $country->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Country created successfully',
            new CountryResource($country)
        );
    }

    public function show(Country $country): JsonResponse
    {
        $country->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Country retrieved successfully',
            new CountryResource($country)
        );
    }

    public function update(CountriesUpdateRequest $request, Country $country): JsonResponse
    {
        $country->update($request->validated());
        $country->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Country updated successfully',
            new CountryResource($country)
        );
    }

    public function destroy(Country $country): JsonResponse
    {
        $country->delete();

        return ApiResponse::delete('Country deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Country::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $countries = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed countries retrieved successfully',
            $countries,
            CountryResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $country = Country::onlyTrashed()->findOrFail($id);
        $country->restore();
        $country->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Country restored successfully',
            new CountryResource($country)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $country = Country::onlyTrashed()->findOrFail($id);
        $country->forceDelete();

        return ApiResponse::delete('Country permanently deleted successfully');
    }
}