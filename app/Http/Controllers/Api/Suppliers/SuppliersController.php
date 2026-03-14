<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Helpers\FeatureHelper;
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
        
        $query = $this->supplierQuery($request);

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

        // Create supplier
        $supplier = Supplier::create($data);
        // Handle document uploads if present
        if ($request->hasFile('documents')) {
            $supplier->createDocuments($request->file('documents'), [
                'type' => 'default',
                'is_public' => false
            ]);
        }

        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'currency.activeRate:id,currency_id,rate',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
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
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'currency.activeRate:id,currency_id,rate',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::show(
            'Supplier retrieved successfully',
            new SupplierResource($supplier)
        );
    }

    public function update(SuppliersUpdateRequest $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validated();

        $supplier->update($data);

        if ($request->hasFile('attachments')) {
            $supplier->updateDocuments($request->file('attachments'), [
                'type' => 'default',
                'is_public' => false
            ]);
        }

        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'currency.activeRate:id,currency_id,rate',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Supplier updated successfully',
            new SupplierResource($supplier)
        );
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        // Soft delete all documents when supplier is deleted
        $supplier->deleteDocuments();
        
        // Soft delete supplier
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
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'currency.activeRate:id,currency_id,rate',
                'createdBy:id,name',
                'updatedBy:id,name',
                'documents' => function($query) {
                    $query->withTrashed();
                }
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
        
        // Restore supplier first
        $supplier->restore();
        
        // Restore all associated documents
        $supplier->restoreDocuments();
        
        $supplier->load([
            'supplierType:id,name',
            'country:id,name,code',
            'paymentTerm:id,name,days,type',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'currency.activeRate:id,currency_id,rate',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Supplier restored successfully',
            new SupplierResource($supplier)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        
        // Permanently delete all documents and their files when supplier is force deleted
        $supplier->forceDeleteDocuments();
        
        // Permanently delete supplier
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
    public function stats(Request $request): JsonResponse
    {
        $isMultiCurrencyEnabled = FeatureHelper::isMultiCurrency();
        $query = $this->supplierQuery($request);

        if ($isMultiCurrencyEnabled) {
            $suppliers = (clone $query)->with('currency:id,code')->get();
            $totalBalance = round($this->sumBalanceInUsd($suppliers, true), 2);
        } else {
            $totalBalance = round((clone $query)->sum('current_balance'), 2);
        }

        $stats = [
            // 'total_suppliers' => (clone $query)->count(),
            // 'active_suppliers' => (clone $query)->where('is_active', true)->count(),
            // 'inactive_suppliers' => (clone $query)->where('is_active', false)->count(),
            // 'trashed_suppliers' => Supplier::onlyTrashed()->count(),
            'total_supplier_balance_usd' => $totalBalance,
            // 'suppliers_by_country' => (clone $query)->with('country:id,name')
            //     ->selectRaw('country_id, count(*) as count')
            //     ->groupBy('country_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'suppliers_by_type' => (clone $query)->with('supplierType:id,name')
            //     ->selectRaw('supplier_type_id, count(*) as count')
            //     ->groupBy('supplier_type_id')
            //     ->having('count', '>', 0)
            //     ->get(),
        ];

        return ApiResponse::show('Supplier statistics retrieved successfully', $stats);
    }

    private function sumBalanceInUsd($suppliers, bool $convertCurrency): float
    {
        return $suppliers->sum(function ($supplier) use ($convertCurrency) {
            $balance = $supplier->current_balance ?? 0;

            if (!$convertCurrency || ($supplier->currency && strtoupper($supplier->currency->code) === 'USD')) {
                return $balance;
            }

            return CurrencyHelper::toUsd($supplier->currency_id, $balance);
        });
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
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'currency.activeRate:id,currency_id,rate',
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

    private function supplierQuery(Request $request) {
        $query = Supplier::query()
            ->with([
                'supplierType:id,name',
                'country:id,name,code',
                'paymentTerm:id,name,days,type',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'currency.activeRate:id,currency_id,rate',
                'createdBy:id,name',
                'updatedBy:id,name',
                'documents'
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

        return $query;
    }
}