<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\CarsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\CarsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\CarResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\Car;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Car::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $cars = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Cars retrieved successfully', $cars, CarResource::class);
    }

    public function store(CarsStoreRequest $request): JsonResponse
    {
        $car = Car::create($request->validated());
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Car created successfully', new CarResource($car));
    }

    public function show(Car $car): JsonResponse
    {
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Car retrieved successfully', new CarResource($car));
    }

    public function update(CarsUpdateRequest $request, Car $car): JsonResponse
    {
        $car->update($request->validated());
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car updated successfully', new CarResource($car));
    }

    public function destroy(Car $car): JsonResponse
    {
        $car->delete();

        return ApiResponse::delete('Car deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Car::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $cars = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed cars retrieved successfully', $cars, CarResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $car = Car::onlyTrashed()->findOrFail($id);
        $car->restore();
        $car->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car restored successfully', new CarResource($car));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $car = Car::onlyTrashed()->findOrFail($id);
        $car->forceDelete();

        return ApiResponse::delete('Car permanently deleted successfully');
    }
}
