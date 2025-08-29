<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomersStoreRequest;
use App\Http\Requests\Api\Customers\CustomersUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Setting;
use App\Models\Setups\Employees\Department;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->with([
                'parent:id,code,name',
                'customerType:id,name',
                'customerGroup:id,name',
                'customerProvince:id,name',
                'customerZone:id,name',
                'salesperson:id,code,name,department_id',
                'salesperson.department:id,name',
                'customerPaymentTerm:id,name,days',
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

        // Filter by customer type
        if ($request->has('customer_type_id')) {
            $query->where('customer_type_id', $request->customer_type_id);
        }

        // Filter by customer group
        if ($request->has('customer_group_id')) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        // Filter by customer province
        if ($request->has('customer_province_id')) {
            $query->where('customer_province_id', $request->customer_province_id);
        }

        // Filter by customer zone
        if ($request->has('customer_zone_id')) {
            $query->where('customer_zone_id', $request->customer_zone_id);
        }

        // Filter by salesperson
        if ($request->has('salesperson_id')) {
            $query->where('salesperson_id', $request->salesperson_id);
        }

        // Filter by parent customer
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', 'LIKE', '%' . $request->city . '%');
        }

        // Balance range filters
        if ($request->has('min_balance')) {
            $query->where('current_balance', '>=', $request->min_balance);
        }

        if ($request->has('max_balance')) {
            $query->where('current_balance', '<=', $request->max_balance);
        }

        // Credit limit filter
        if ($request->boolean('over_credit_limit')) {
            $query->overCreditLimit();
        }

        // Balance status filter
        if ($request->has('balance_status')) {
            $status = $request->balance_status;
            if ($status === 'credit') {
                $query->where('current_balance', '>', 0);
            } elseif ($status === 'debit') {
                $query->where('current_balance', '<', 0);
            } elseif ($status === 'balanced') {
                $query->where('current_balance', '=', 0);
            }
        }

        $customers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customers retrieved successfully',
            $customers,
            CustomerResource::class
        );
    }

    public function store(CustomersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Auto-generate customer code (system generated only)
        $data['code'] = Customer::reserveNextCode();

        // Create customer
        $customer = Customer::create($data);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $customer->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            // Upload documents
            $customer->createDocuments($files, [
                'type' => 'customer_document'
            ]);
        }

        $customer->load([
            'parent:id,code,name',
            'customerType:id,name',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name,department_id',
            'salesperson.department:id,name',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::store(
            'Customer created successfully',
            new CustomerResource($customer)
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'parent:id,code,name',
            'children:id,code,name,current_balance',
            'customerType:id,name',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name,department_id',
            'salesperson.department:id,name',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::show(
            'Customer retrieved successfully',
            new CustomerResource($customer)
        );
    }

    public function update(CustomersUpdateRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();

        // Remove code from data if present (code is system generated only, not updatable)
        unset($data['code']);

        $customer->update($data);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $customer->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            $customer->updateDocuments($files, [
                'type' => 'customer_document'
            ]);
        }

        $customer->load([
            'parent:id,code,name',
            'customerType:id,name',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name,department_id',
            'salesperson.department:id,name',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Customer updated successfully',
            new CustomerResource($customer)
        );
    }

    public function destroy(Customer $customer): JsonResponse
    {
        // Check if customer has children
        if ($customer->hasChildren()) {
            return ApiResponse::customError('Cannot delete customer with child customers. Please handle child customers first.', 422);
        }

        if ($customer->hasDocuments()) {
            $customer->deleteDocuments(); // Soft delete all documents
        }
        
        $customer->delete();

        return ApiResponse::delete('Customer deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Customer::onlyTrashed()
            ->with([
                'parent:id,code,name',
                'customerType:id,name',
                'customerGroup:id,name',
                'customerProvince:id,name',
                'customerZone:id,name',
                'salesperson:id,code,name,department_id',
                'salesperson.department:id,name',
                'customerPaymentTerm:id,name,days',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Apply same filters as index method
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('customer_type_id')) {
            $query->where('customer_type_id', $request->customer_type_id);
        }

        $customers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customers retrieved successfully',
            $customers,
            CustomerResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        
        // Restore customer first
        $customer->restore();
        
        // Restore associated documents if any exist
        $customer->restoreDocuments();
        
        $customer->load([
            'parent:id,code,name',
            'customerType:id,name',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name,department_id',
            'salesperson.department:id,name',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Customer restored successfully',
            new CustomerResource($customer)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        
        // Force delete associated documents (permanently delete files and records)
        $customer->forceDeleteDocuments();
        
        // Permanently delete customer
        $customer->forceDelete();

        return ApiResponse::delete('Customer permanently deleted successfully');
    }

    /**
     * Get the next suggested customer code
     */
    public function getNextCode(): JsonResponse
    {
        $nextCode = Customer::getNextSuggestedCode();
        
        return ApiResponse::show('Next customer code retrieved successfully', [
            'code' => $nextCode,
            'is_available' => true,
            'message' => 'Next available numeric code'
        ]);
    }


    /**
     * Get salespersons from Sales department only
     */
    public function getSalespersons(): JsonResponse
    {
        $salesDepartment = Department::where('name', 'Sales')->first();
        
        if (!$salesDepartment) {
            return ApiResponse::customError('Sales department not found. Please ensure departments are properly seeded.', 404);
        }

        $salespersons = $salesDepartment->employees()
            ->active()
            ->select('id', 'code', 'name', 'department_id')
            ->with('department:id,name')
            ->get();

        return ApiResponse::show('Salespersons retrieved successfully', $salespersons);
    }

    /**
     * Get customer statistics for dashboard
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('is_active', true)->count(),
            'inactive_customers' => Customer::where('is_active', false)->count(),
            'trashed_customers' => Customer::onlyTrashed()->count(),
            'customers_with_balance' => Customer::where('current_balance', '!=', 0)->count(),
            'customers_over_credit_limit' => Customer::whereColumn('current_balance', '>', 'credit_limit')
                ->whereNotNull('credit_limit')->count(),
            'total_customer_balance' => Customer::sum('current_balance'),
            'customers_by_type' => Customer::with('customerType:id,name')
                ->selectRaw('customer_type_id, count(*) as count')
                ->groupBy('customer_type_id')
                ->having('count', '>', 0)
                ->get(),
            'customers_by_province' => Customer::with('customerProvince:id,name')
                ->selectRaw('customer_province_id, count(*) as count')
                ->groupBy('customer_province_id')
                ->having('count', '>', 0)
                ->get(),
            'customers_by_zone' => Customer::with('customerZone:id,name')
                ->selectRaw('customer_zone_id, count(*) as count')
                ->groupBy('customer_zone_id')
                ->having('count', '>', 0)
                ->get(),
        ];

        return ApiResponse::show('Customer statistics retrieved successfully', $stats);
    }

    /**
     * Upload documents for a customer
     */
    public function uploadDocuments(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|max:15',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $files = $request->file('documents');
        
        // Validate each document file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $customer->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }
        
        // Upload documents
        $uploadedDocuments = $customer->createDocuments($files, [
            'type' => 'customer_document'
        ]);

        return ApiResponse::store(
            'Documents uploaded successfully',
            [
                'uploaded_count' => $uploadedDocuments->count(),
                'documents' => $uploadedDocuments->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'file_name' => $doc->file_name,
                        'thumbnail_url' => $doc->thumbnail_url,
                        'download_url' => $doc->download_url,
                    ];
                })
            ]
        );
    }

    /**
     * Delete specific documents for a customer
     */
    public function deleteDocuments(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this customer
        $documentCount = $customer->documents()
            ->whereIn('id', $request->document_ids)
            ->count();

        if ($documentCount !== count($request->document_ids)) {
            return ApiResponse::customError('Some documents do not belong to this customer', 403);
        }

        // Delete the documents
        $deleted = $customer->deleteDocuments($request->document_ids);

        return ApiResponse::delete(
            'Documents deleted successfully',
            ['deleted_count' => $deleted ? count($request->document_ids) : 0]
        );
    }

    /**
     * Get documents for a specific customer
     */
    public function getDocuments(Customer $customer): JsonResponse
    {
        $documents = $customer->documents()->get();

        return ApiResponse::show(
            'Customer documents retrieved successfully',
            $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'original_name' => $doc->original_name,
                    'file_name' => $doc->file_name,
                    'file_size' => $doc->file_size,
                    'file_size_human' => $doc->file_size_human,
                    'thumbnail_url' => $doc->thumbnail_url,
                    'download_url' => $doc->download_url,
                    'uploaded_at' => $doc->created_at,
                ];
            })
        );
    }
}
