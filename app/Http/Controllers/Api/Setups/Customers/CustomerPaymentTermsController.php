<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Customers\CustomerPaymentTermsStoreRequest;
use App\Http\Requests\Api\Setups\Customers\CustomerPaymentTermsUpdateRequest;
use App\Http\Resources\Api\Setups\Customers\CustomerPaymentTermResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPaymentTermsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerPaymentTerm::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerPaymentTerms = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer payment terms retrieved successfully',
            $customerPaymentTerms,
            CustomerPaymentTermResource::class
        );
    }

    public function store(CustomerPaymentTermsStoreRequest $request): JsonResponse
    {
        $customerPaymentTerm = CustomerPaymentTerm::create($request->validated());
        $customerPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Customer payment term created successfully',
            new CustomerPaymentTermResource($customerPaymentTerm)
        );
    }

    public function show(CustomerPaymentTerm $customerPaymentTerm): JsonResponse
    {
        $customerPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Customer payment term retrieved successfully',
            new CustomerPaymentTermResource($customerPaymentTerm)
        );
    }

    public function update(CustomerPaymentTermsUpdateRequest $request, CustomerPaymentTerm $customerPaymentTerm): JsonResponse
    {
        $customerPaymentTerm->update($request->validated());
        $customerPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer payment term updated successfully',
            new CustomerPaymentTermResource($customerPaymentTerm)
        );
    }

    public function destroy(CustomerPaymentTerm $customerPaymentTerm): JsonResponse
    {
        $customerPaymentTerm->delete();

        return ApiResponse::delete('Customer payment term deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerPaymentTerm::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerPaymentTerms = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer payment terms retrieved successfully',
            $customerPaymentTerms,
            CustomerPaymentTermResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $customerPaymentTerm = CustomerPaymentTerm::onlyTrashed()->findOrFail($id);
        $customerPaymentTerm->restore();
        $customerPaymentTerm->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer payment term restored successfully',
            new CustomerPaymentTermResource($customerPaymentTerm)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $customerPaymentTerm = CustomerPaymentTerm::onlyTrashed()->findOrFail($id);
        $customerPaymentTerm->forceDelete();

        return ApiResponse::delete('Customer payment term permanently deleted successfully');
    }
}
