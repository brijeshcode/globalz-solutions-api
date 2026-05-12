<?php

namespace App\Http\Controllers\Api\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Vehicle\CarsStoreRequest;
use App\Http\Requests\Api\Vehicle\CarsUpdateRequest;
use App\Http\Resources\Api\Vehicle\CarResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vehicle\Car;
use App\Models\Vehicle\CarRefill;
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

    public function transactions(int $id): JsonResponse
    {
        Car::findOrFail($id);

        $refills = CarRefill::where('car_id', $id)
            ->with(['gasStation:id,name', 'driver:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $totalKmDriven  = (float) $refills->sum('km_driven');
        $totalAmount    = (float) $refills->sum('amount');
        $avgKmCost      = $totalKmDriven > 0 ? round($totalAmount / $totalKmDriven, 4) : null;

        $result = $refills->map(fn($r) => [
            'type'           => 'refill',
            'id'             => $r->id,
            'code'           => $r->code,
            'date'           => $r->date?->format('Y-m-d H:i:s'),
            'gas_station'    => $r->gasStation ? ['id' => $r->gasStation->id, 'name' => $r->gasStation->name] : null,
            'driver'         => $r->driver ? ['id' => $r->driver->id, 'name' => $r->driver->name] : null,
            'odometer'       => $r->odometer,
            'km_driven'      => $r->km_driven,
            'amount'         => $r->amount,
            'km_cost'        => ($r->km_driven > 0) ? round($r->amount / $r->km_driven, 4) : null,
            'invoices_count' => $r->invoices_count,
            'note'           => $r->note,
            'created_by'     => $r->createdBy ? ['name' => $r->createdBy->name, 'at' => $r->created_at?->format('Y-m-d H:i:s')] : null,
            'updated_by'     => $r->updatedBy ? ['name' => $r->updatedBy->name, 'at' => $r->updated_at?->format('Y-m-d H:i:s')] : null,
        ])->reverse()->values();

        return ApiResponse::show('Car transactions retrieved successfully', [
            'stats' => [
                'km_driven'    => $totalKmDriven,
                'amount'       => $totalAmount,
                'avg_km_cost'  => $avgKmCost,
            ],
            'transactions' => $result,
        ]);
    }
}
