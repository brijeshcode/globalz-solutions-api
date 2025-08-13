<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\SuppliersStoreRequest;
use App\Http\Requests\Api\Setups\SuppliersUpdateRequest;
use App\Http\Resources\Api\Setups\SupplierResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Supplier;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->with([
                'supplierType:id,name',
                'country:id,name,code',
                'paymentTerm:id,name,days,type',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by supplier type
        if ($request->has('supplier_type_id')) {
            $query->where('supplier_type_id', $request->supplier_type_id);
        }

        // Filter by country
        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Filter by payment term
        if ($request->has('payment_term_id')) {
            $query->where('payment_term_id', $request->payment_term_id);
        }

        // Filter by currency
        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        // Balance range filters
        if ($request->has('min_balance')) {
            $query->where('opening_balance', '>=', $request->min_balance);
        }

        if ($request->has('max_balance')) {
            $query->where('opening_balance', '<=', $request->max_balance);
        }

        $suppliers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Suppliers retrieved successfully',
            $suppliers,
            SupplierResource::class
        );
    }

    public function store(SuppliersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Generate code if not provided
         $data['code'] = $this->generateSupplierCode();

        $supplier = Supplier::create($data);
        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store(
            'Supplier created successfully',
            new SupplierResource($supplier)
        );
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Supplier retrieved successfully',
            new SupplierResource($supplier)
        );
    }

    public function update(SuppliersUpdateRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());
        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Supplier updated successfully',
            new SupplierResource($supplier)
        );
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return ApiResponse::delete('Supplier deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Supplier::onlyTrashed()
            ->with([
                'supplierType:id,name',
                'country:id,name,code',
                'paymentTerm:id,name,days,type',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Apply same filters as index method
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('supplier_type_id')) {
            $query->where('supplier_type_id', $request->supplier_type_id);
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $suppliers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed suppliers retrieved successfully',
            $suppliers,
            SupplierResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->restore();
        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Supplier restored successfully',
            new SupplierResource($supplier)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        $supplier->forceDelete();

        return ApiResponse::delete('Supplier permanently deleted successfully');
    }

    /**
     * Generate unique supplier code starting from 1000
     */
    private function generateSupplierCode(): string
    {
        // Get all codes and filter numeric ones in PHP to avoid database-specific functions
        $numericCodes = Supplier::withTrashed()
            ->pluck('code')
            ->filter(function ($code) {
                return is_numeric($code) && (int)$code == $code;
            })
            ->map(function ($code) {
                return (int) $code;
            })
            ->sort()
            ->values();

        if ($numericCodes->isNotEmpty()) {
            $nextCode = $numericCodes->max() + 1;
        } else {
            $nextCode = 1000; // Starting code as per requirements
        }

        // Ensure uniqueness in case of race conditions
        while (Supplier::withTrashed()->where('code', (string) $nextCode)->exists()) {
            $nextCode++;
        }

        return (string) $nextCode;
    }

    /**
     * Get supplier statistics for dashboard
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::where('is_active', true)->count(),
            'inactive_suppliers' => Supplier::where('is_active', false)->count(),
            'trashed_suppliers' => Supplier::onlyTrashed()->count(),
            'total_opening_balance' => Supplier::sum('opening_balance'),
            'suppliers_by_country' => Supplier::with('country:id,name')
                ->selectRaw('country_id, count(*) as count')
                ->groupBy('country_id')
                ->having('count', '>', 0)
                ->get(),
            'suppliers_by_type' => Supplier::with('supplierType:id,name')
                ->selectRaw('supplier_type_id, count(*) as count')
                ->groupBy('supplier_type_id')
                ->having('count', '>', 0)
                ->get(),
        ];

        return ApiResponse::show('Supplier statistics retrieved successfully', $stats);
    }

    /**
     * Export suppliers for reports
     */
    public function export(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->with([
                'supplierType:id,name',
                'country:id,name,code',
                'currency:id,name,code,symbol'
            ])
            ->select(['id', 'code', 'name', 'opening_balance', 'supplier_type_id', 'country_id', 'currency_id', 'is_active']);

        // Apply filters from request
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('supplier_type_id')) {
            $query->where('supplier_type_id', $request->supplier_type_id);
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $suppliers = $query->get();

        $exportData = $suppliers->map(function ($supplier) {
            return [
                'code' => $supplier->code,
                'name' => $supplier->name,
                'balance' => $supplier->balance,
                'supplier_type' => $supplier->supplierType?->name,
                'country' => $supplier->country?->name,
                'currency' => $supplier->currency?->code,
                'status' => $supplier->is_active ? 'Active' : 'Inactive',
            ];
        });

        return ApiResponse::show('Suppliers exported successfully', $exportData);
    }
}