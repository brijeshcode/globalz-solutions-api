<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\SupplierPaymentTermsStoreRequest;
use App\Http\Requests\Api\Setups\SupplierPaymentTermsUpdateRequest;
use App\Http\Resources\Api\Setups\SupplierPaymentTermResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\SupplierPaymentTerm;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentTermsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = SupplierPaymentTerm::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $supplierPaymentTerms = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Supplier payment terms retrieved successfully',
            $supplierPaymentTerms,
            SupplierPaymentTermResource::class
        );
    }

    public function store(SupplierPaymentTermsStoreRequest $request): JsonResponse
    {
        $supplierPaymentTerm = SupplierPaymentTerm::create($request->validated());
        $supplierPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Supplier payment term created successfully',
            new SupplierPaymentTermResource($supplierPaymentTerm)
        );
    }

    public function show(SupplierPaymentTerm $supplierPaymentTerm): JsonResponse
    {
        $supplierPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Supplier payment term retrieved successfully',
            new SupplierPaymentTermResource($supplierPaymentTerm)
        );
    }

    public function update(SupplierPaymentTermsUpdateRequest $request, SupplierPaymentTerm $supplierPaymentTerm): JsonResponse
    {
        $supplierPaymentTerm->update($request->validated());
        $supplierPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Supplier payment term updated successfully',
            new SupplierPaymentTermResource($supplierPaymentTerm)
        );
    }

    public function destroy(SupplierPaymentTerm $supplierPaymentTerm): JsonResponse
    {
        $supplierPaymentTerm->delete();

        return ApiResponse::delete('Supplier payment term deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = SupplierPaymentTerm::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $supplierPaymentTerms = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed supplier payment terms retrieved successfully',
            $supplierPaymentTerms,
            SupplierPaymentTermResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $supplierPaymentTerm = SupplierPaymentTerm::onlyTrashed()->findOrFail($id);
        $supplierPaymentTerm->restore();
        $supplierPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Supplier payment term restored successfully',
            new SupplierPaymentTermResource($supplierPaymentTerm)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $supplierPaymentTerm = SupplierPaymentTerm::onlyTrashed()->findOrFail($id);
        $supplierPaymentTerm->forceDelete();

        return ApiResponse::delete('Supplier payment term permanently deleted successfully');
    }
}