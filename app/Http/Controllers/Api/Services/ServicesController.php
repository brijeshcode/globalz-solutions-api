<?php

namespace App\Http\Controllers\Api\Services;

use App\Helpers\CurrencyHelper;
use App\Helpers\FeatureHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Services\ServiceStoreRequest;
use App\Http\Requests\Api\Services\ServiceUpdateRequest;
use App\Http\Resources\Api\Services\ServiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Services\Services;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Services::query()
            ->with(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('tax_code_id')) {
            $query->where('tax_code_id', $request->tax_code_id);
        }

        $services = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Services retrieved successfully',
            $services,
            ServiceResource::class
        );
    }

    public function store(ServiceStoreRequest $request): JsonResponse
    {
        $data = $this->applyUsdPricing($request->validated());

        $service = Services::create($data);
        $service->load(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Service created successfully',
            new ServiceResource($service)
        );
    }

    public function show(Services $service): JsonResponse
    {
        $service->load(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Service retrieved successfully',
            new ServiceResource($service)
        );
    }

    public function update(ServiceUpdateRequest $request, Services $service): JsonResponse
    {
        $data = $request->validated();

        // Recompute amount_usd / rate when any pricing input changes.
        if (array_intersect(['amount', 'currency_id', 'currency_rate'], array_keys($data))) {
            $priced = $this->applyUsdPricing(array_merge($service->only(['amount', 'currency_id', 'currency_rate']), $data));
            $data['amount_usd'] = $priced['amount_usd'];
            $data['currency_rate'] = $priced['currency_rate'];
        }

        $service->update($data);
        $service->load(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Service updated successfully',
            new ServiceResource($service)
        );
    }

    public function destroy(Services $service): JsonResponse
    {
        $service->delete();

        return ApiResponse::delete('Service deleted successfully');
    }

    public function active(): JsonResponse
    {
        $services = Services::active()
            ->orderBy('name')
            ->get(['id', 'name', 'tax_code_id', 'amount', 'amount_usd', 'currency_id', 'currency_rate']);

        return ApiResponse::show('Active services retrieved successfully', $services);
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Services::onlyTrashed()
            ->with(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $services = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed services retrieved successfully',
            $services,
            ServiceResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $service = Services::onlyTrashed()->findOrFail($id);
        $service->restore();
        $service->load(['taxCode:id,name,code,tax_percent', 'currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Service restored successfully',
            new ServiceResource($service)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $service = Services::onlyTrashed()->findOrFail($id);
        $service->forceDelete();

        return ApiResponse::delete('Service permanently deleted successfully');
    }

    /**
     * Set amount_usd (and currency_rate) on the payload.
     * With multi-currency off the system is single-currency: rate = 1 and amount_usd = amount.
     * Otherwise amount_usd is converted from the selected currency (local currency = USD-equivalent).
     */
    private function applyUsdPricing(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);

        if (!FeatureHelper::isMultiCurrency()) {
            $data['currency_rate'] = 1;
            $data['amount_usd'] = $amount;

            return $data;
        }

        $data['amount_usd'] = !empty($data['currency_id'])
            ? CurrencyHelper::toUsd($data['currency_id'], $amount, $data['currency_rate'] ?? null)
            : $amount;

        return $data;
    }
}
