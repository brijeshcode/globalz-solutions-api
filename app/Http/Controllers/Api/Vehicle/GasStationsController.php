<?php

namespace App\Http\Controllers\Api\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Vehicle\GasStationsStoreRequest;
use App\Http\Requests\Api\Vehicle\GasStationsUpdateRequest;
use App\Http\Resources\Api\Vehicle\GasStationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vehicle\CarRefill;
use App\Models\Vehicle\GasStation;
use App\Models\Vehicle\GasStationPayment;
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

    public function transactions(int $id): JsonResponse
    {
        GasStation::findOrFail($id);

        $refills = CarRefill::where('gas_station_id', $id)
            ->with(['car:id,name', 'driver:id,name', 'createdBy:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn($r) => [
                'type'           => 'refill',
                'id'             => $r->id,
                'code'           => $r->code,
                'date'           => $r->date?->format('Y-m-d H:i:s'),
                'amount'         => $r->amount,
                'car'            => $r->car ? ['id' => $r->car->id, 'name' => $r->car->name] : null,
                'driver'         => $r->driver ? ['id' => $r->driver->id, 'name' => $r->driver->name] : null,
                'odometer'       => $r->odometer,
                'km_driven'      => $r->km_driven,
                'km_cost'        => ($r->km_driven > 0) ? round($r->amount / $r->km_driven, 4) : null,
                'invoices_count' => $r->invoices_count,
                'created_by'     => $r->createdBy?->name,
                'balance'        => null,
            ]);

        $payments = GasStationPayment::where('gas_station_id', $id)
            ->with(['account:id,name', 'createdBy:id,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'type'       => 'payment',
                'id'         => $p->id,
                'code'       => $p->code,
                'date'       => $p->date?->format('Y-m-d H:i:s'),
                'amount'     => $p->amount,
                'account'    => $p->account ? ['id' => $p->account->id, 'name' => $p->account->name] : null,
                'created_by' => $p->createdBy?->name,
                'balance'    => null,
            ]);

        $combined = $refills->concat($payments)
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values();

        $runningBalance = '0.0000';
        $result = $combined->map(function ($item) use (&$runningBalance) {
            if ($item['type'] === 'refill') {
                $runningBalance = bcadd($runningBalance, (string) $item['amount'], 4);
            } else {
                $runningBalance = bcsub($runningBalance, (string) $item['amount'], 4);
            }
            $item['balance'] = $runningBalance;
            return $item;
        });

        return ApiResponse::show('Gas station transactions retrieved successfully', $result);
    }
}
