<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\TaxCodeStoreRequest;
use App\Http\Requests\Api\Setups\TaxCodeUpdateRequest;
use App\Http\Resources\Api\Setups\TaxCodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\TaxCode;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxCodesController extends Controller
{
    use HasPagination;
    /**
     * Display a listing of tax codes with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxCode::query()
            ->searchable($request)
            ->sortable($request);
 

        // Apply filters
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('is_active')) {
            $query->active($request->boolean('is_active'));
        }

        if ($request->filled('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }
  
        // Paginate results
        $perPage = $request->get('per_page', 20);
        $taxCodes = $query->paginate($perPage);

        return ApiResponse::paginated(
            'Tax codes retrieved successfully',
            $taxCodes,
            TaxCodeResource::class
        );
    }

    /**
     * Store a newly created tax code.
     */
    public function store(TaxCodeStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // If this is set as default, unset any existing default
        if ($validated['is_default'] ?? false) {
            TaxCode::where('is_default', true)->update(['is_default' => false]);
        }

        $taxCode = TaxCode::create($validated);

        return ApiResponse::store(
            'Tax code created successfully',
            new TaxCodeResource($taxCode)
        );
    }

    /**
     * Display the specified tax code.
     */
    public function show(TaxCode $taxCode): JsonResponse
    {
        return ApiResponse::show(
            'Tax code retrieved successfully',
            new TaxCodeResource($taxCode)
        );
    }

    /**
     * Update the specified tax code.
     */
    public function update(TaxCodeUpdateRequest $request, TaxCode $taxCode): JsonResponse
    {
        $validated = $request->validated();
        
        // If this is set as default, unset any existing default (except current one)
        if ($validated['is_default'] ?? false) {
            TaxCode::where('is_default', true)
                   ->where('id', '!=', $taxCode->id)
                   ->update(['is_default' => false]);
        }

        $taxCode->update($validated);

        return ApiResponse::update(
            'Tax code updated successfully',
            new TaxCodeResource($taxCode->fresh())
        );
    }

    /**
     * Remove the specified tax code from storage.
     */
    public function destroy(TaxCode $taxCode): JsonResponse
    {
        // Check if tax code is being used by items
        // if ($taxCode->items()->exists()) {
        //     return ApiResponse::customError(
        //         'Cannot delete tax code. It is being used by one or more items.',
        //         400
        //     );
        // }

        $taxCode->delete();

        return ApiResponse::delete('Tax code deleted successfully');
    }

    /**
     * Get active tax codes for dropdowns.
     */
    public function active(): JsonResponse
    {
        $taxCodes = TaxCode::active()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'tax_percent', 'type', 'is_active']);

        return ApiResponse::show(
            'Active tax codes retrieved successfully',
            $taxCodes
        );
    }

    /**
     * Get the default tax code.
     */
    public function getDefault(): JsonResponse
    {
        $defaultTaxCode = TaxCode::getDefault();

        if (!$defaultTaxCode) {
            return ApiResponse::customError('No default tax code found', 404);
        }

        return ApiResponse::show(
            'Default tax code retrieved successfully',
            new TaxCodeResource($defaultTaxCode)
        );
    }

    /**
     * Set a tax code as default.
     */
    public function setDefault(TaxCode $taxCode): JsonResponse
    {
        // Unset any existing default
        TaxCode::where('is_default', true)->update(['is_default' => false]);
        
        // Set this tax code as default
        $taxCode->update(['is_default' => true]);

        return ApiResponse::update(
            'Tax code set as default successfully',
            new TaxCodeResource($taxCode->fresh())
        );
    }

    /**
     * Calculate tax for a given amount using specified tax code.
     */
    public function calculateTax(Request $request, TaxCode $taxCode): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $amount = $request->get('amount');
        
        $calculations = [
            'base_amount' => $amount,
            'tax_amount' => $taxCode->calculateTaxAmount($amount),
            'total_amount' => $taxCode->calculateTotalWithTax($amount),
            'tax_code' => [
                'id' => $taxCode->id,
                'code' => $taxCode->code,
                'name' => $taxCode->name,
                'tax_percent' => $taxCode->tax_percent,
                'type' => $taxCode->type,
            ]
        ];

        return ApiResponse::show(
            'Tax calculation completed successfully',
            $calculations
        );
    }

    /**
     * Bulk delete tax codes.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'tax_code_ids' => 'required|array',
            'tax_code_ids.*' => 'required|integer|exists:tax_codes,id',
        ]);

        $taxCodeIds = $request->tax_code_ids;
        $taxCodes = TaxCode::whereIn('id', $taxCodeIds)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($taxCodes as $taxCode) {
            // Check if tax code is being used
            // if ($taxCode->items()->exists()) {
            //     $errors[] = "Tax code '{$taxCode->code}' cannot be deleted as it is being used by items.";
            //     continue;
            // }

            if ($taxCode->delete()) {
                $deletedCount++;
            }
        }

        $message = "Successfully deleted {$deletedCount} tax codes";
        if (!empty($errors)) {
            $message .= ". Some tax codes could not be deleted.";
        }

        return ApiResponse::delete($message, [
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ]);
    }
}