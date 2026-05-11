<?php

namespace App\Http\Controllers\Api\Setups\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Vehicle\GasStationsStoreRequest;
use App\Http\Requests\Api\Setups\Vehicle\GasStationsUpdateRequest;
use App\Http\Resources\Api\Setups\Vehicle\GasStationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Vehicle\GasStation;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasStationsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = GasStation::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $stations = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Gas stations retrieved successfully', $stations, GasStationResource::class);
    }

    public function store(GasStationsStoreRequest $request): JsonResponse
    {
        $station = GasStation::create($request->validated());
        $station->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Gas station created successfully', new GasStationResource($station));
    }

    public function show(GasStation $gasStation): JsonResponse
    {
        $gasStation->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Gas station retrieved successfully', new GasStationResource($gasStation));
    }

    public function update(GasStationsUpdateRequest $request, GasStation $gasStation): JsonResponse
    {
        $gasStation->update($request->validated());
        $gasStation->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station updated successfully', new GasStationResource($gasStation));
    }

    public function destroy(GasStation $gasStation): JsonResponse
    {
        $gasStation->delete();

        return ApiResponse::delete('Gas station deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = GasStation::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $stations = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed gas stations retrieved successfully', $stations, GasStationResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $station = GasStation::onlyTrashed()->findOrFail($id);
        $station->restore();
        $station->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station restored successfully', new GasStationResource($station));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $station = GasStation::onlyTrashed()->findOrFail($id);
        $station->forceDelete();

        return ApiResponse::delete('Gas station permanently deleted successfully');
    }
}
