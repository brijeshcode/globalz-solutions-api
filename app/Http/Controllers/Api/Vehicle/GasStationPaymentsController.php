<?php

namespace App\Http\Controllers\Api\Vehicle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Vehicle\GasStationPaymentsStoreRequest;
use App\Http\Requests\Api\Vehicle\GasStationPaymentsUpdateRequest;
use App\Http\Resources\Api\Vehicle\GasStationPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Vehicle\GasStationPayment;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasStationPaymentsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = GasStationPayment::query()
            ->with(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('gas_station_id')) {
            $query->where('gas_station_id', $request->gas_station_id);
        }
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->has('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Gas station payments retrieved successfully', $payments, GasStationPaymentResource::class);
    }

    public function store(GasStationPaymentsStoreRequest $request): JsonResponse
    {
        $payment = GasStationPayment::create($request->validated());
        $payment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store('Gas station payment created successfully', new GasStationPaymentResource($payment));
    }

    public function show(GasStationPayment $gasStationPayment): JsonResponse
    {
        $gasStationPayment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show('Gas station payment retrieved successfully', new GasStationPaymentResource($gasStationPayment));
    }

    public function update(GasStationPaymentsUpdateRequest $request, GasStationPayment $gasStationPayment): JsonResponse
    {
        $gasStationPayment->update($request->validated());

        $gasStationPayment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station payment updated successfully', new GasStationPaymentResource($gasStationPayment));
    }

    public function destroy(GasStationPayment $gasStationPayment): JsonResponse
    {
        $gasStationPayment->delete();

        return ApiResponse::delete('Gas station payment deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = GasStationPayment::onlyTrashed()
            ->with(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed gas station payments retrieved successfully', $payments, GasStationPaymentResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $payment = GasStationPayment::onlyTrashed()->findOrFail($id);
        $payment->restore();
        $payment->load(['gasStation', 'account', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update('Gas station payment restored successfully', new GasStationPaymentResource($payment));
    }

    public function forceDelete(int $id): JsonResponse
    {
        $payment = GasStationPayment::onlyTrashed()->findOrFail($id);
        $payment->forceDelete();

        return ApiResponse::delete('Gas station payment permanently deleted successfully');
    }
}
