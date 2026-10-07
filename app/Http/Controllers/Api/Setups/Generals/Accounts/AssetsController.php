<?php

namespace App\Http\Controllers\Api\Setups\Generals\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Generals\Accounts\AssetStoreRequest;
use App\Http\Requests\Api\Setups\Generals\Accounts\AssetUpdateRequest;
use App\Http\Resources\Api\Setups\Generals\Accounts\AssetsResource;
use App\Http\Responses\ApiResponse;
use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Models\Setups\Generals\Accounts\Asset;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetsController extends Controller
{
    use HasPagination;

    public function __construct()
    {
        if (!RoleHelper::canSuperAdmin()) {
            abort(403, 'Unauthorized. Super admin access required.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->with(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $assets = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Assets retrieved successfully',
            $assets,
            AssetsResource::class
        );
    }

    public function store(AssetStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['amount_usd'] = $this->toUsd($data);

        $asset = Asset::create($data);
        $asset->load(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Asset created successfully',
            new AssetsResource($asset)
        );
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Asset retrieved successfully',
            new AssetsResource($asset)
        );
    }

    public function update(AssetUpdateRequest $request, Asset $asset): JsonResponse
    {
        $data = $request->validated();
        $data['amount_usd'] = $this->toUsd($data);

        $asset->update($data);
        $asset->load(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Asset updated successfully',
            new AssetsResource($asset)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toUsd(array $data): float
    {
        $currencyId = $data['currency_id'] ?? CurrencyHelper::getLocalCurrencyId();

        return CurrencyHelper::toUsd(
            (int) $currencyId,
            (float) $data['amount'],
            isset($data['currency_rate']) ? (float) $data['currency_rate'] : null
        );
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();

        return ApiResponse::delete('Asset deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Asset::onlyTrashed()
            ->with(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $assets = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed assets retrieved successfully',
            $assets,
            AssetsResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->restore();
        $asset->load(['currency:id,name,code', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Asset restored successfully',
            new AssetsResource($asset)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->forceDelete();

        return ApiResponse::delete('Asset permanently deleted successfully');
    }
}
