<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\CarRefillsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\CarRefillsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\CarRefillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\CarRefill;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarRefillsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CarRefill::query()
            ->with(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }
        if ($request->has('gas_station_id')) {
            $query->where('gas_station_id', $request->gas_station_id);
        }
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $refills = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Car refills retrieved successfully', $refills, CarRefillResource::class);
    }

    public function store(CarRefillsStoreRequest $request): JsonResponse
    {
        $refill = CarRefill::create($request->validated());
        $refill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Car refill created successfully', new CarRefillResource($refill));
    }

    public function show(CarRefill $carRefill): JsonResponse
    {
        $carRefill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Car refill retrieved successfully', new CarRefillResource($carRefill));
    }

    public function update(CarRefillsUpdateRequest $request, CarRefill $carRefill): JsonResponse
    {
        $validated = $request->validated();

        $oldAmount       = (float) $carRefill->amount;
        $oldGasStationId = $carRefill->gas_station_id;
        $odometerChanged = isset($validated['odometer']) && (float) $validated['odometer'] !== (float) $carRefill->odometer;

        if ($oldGasStationId !== ($validated['gas_station_id'] ?? $oldGasStationId)) {
            $carRefill->gasStation()->decrement('balance', $oldAmount);
        }

        $carRefill->fill($validated);

        if ($odometerChanged || isset($validated['car_id'])) {
            $carRefill->km_driven = $carRefill->calculateKmDriven();
        }

        $carRefill->save();

        $newAmount       = (float) $carRefill->amount;
        $newGasStationId = $carRefill->gas_station_id;

        if ($oldGasStationId !== $newGasStationId) {
            $carRefill->gasStation()->increment('balance', $newAmount);
        } else {
            $diff = $newAmount - $oldAmount;
            if ($diff !== 0.0) {
                $carRefill->gasStation()->increment('balance', $diff);
            }
        }

        if ($odometerChanged) {
            $carRefill->recalculateNextRefill();
        }

        $carRefill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car refill updated successfully', new CarRefillResource($carRefill));
    }

    public function destroy(CarRefill $carRefill): JsonResponse
    {
        $carRefill->delete();

        return ApiResponse::delete('Car refill deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CarRefill::onlyTrashed()
            ->with(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $refills = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed car refills retrieved successfully', $refills, CarRefillResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $refill = CarRefill::onlyTrashed()->findOrFail($id);
        $refill->restore();
        $refill->load(['car', 'gasStation', 'driver', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Car refill restored successfully', new CarRefillResource($refill));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $refill = CarRefill::onlyTrashed()->findOrFail($id);
        $refill->forceDelete();

        return ApiResponse::delete('Car refill permanently deleted successfully');
    }
}
