<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomersStoreRequest;
use App\Http\Requests\Api\Customers\CustomersUpdateRequest;
use App\Http\Requests\Api\Customers\CustomersImportRequest;
use App\Http\Resources\Api\Customers\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Imports\CustomersImport;
use App\Models\Customers\Customer;
use App\Models\Items\PriceList;
use App\Models\Setups\Employees\Department;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CustomersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->customerQuery($request);
        $query->withSum(['sales' => function ($query) {
            $query->approved();
        }], 'total_usd');

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

        // Set default price lists if not provided
        // $defaultPriceList = PriceList::getDefault();
        // if ($defaultPriceList) {
        //     if (empty($data['price_list_id_INV'])) {
        //         $data['price_list_id_INV'] = $defaultPriceList->id;
        //     }
        //     if (empty($data['price_list_id_INX'])) {
        //         $data['price_list_id_INX'] = $defaultPriceList->id;
        //     }
        // }

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
            'priceListINV:id,code,description',
            'priceListINX:id,code,description',
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
            // 'parent:id,code,name',
            // 'children:id,code,name,current_balance',
            'priceListINV:id,code,description',
            'priceListINX:id,code,description',
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
            // 'parent:id,code,name',
            'customerType:id,name',
            'priceListINV:id,code,description',
            'priceListINX:id,code,description',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name,department_id',
            // 'salesperson.department:id,name',
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
        if(!RoleHelper::canAdmin()){
            return ApiResponse::customError('Only admins can delete customer', 403);
        }
        
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
            'priceListINV:id,code,description',
            'priceListINX:id,code,description',
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
    public function stats(Request $request): JsonResponse
    {
        $query = $this->customerQuery($request);

        $stats = [
            'total_customers' =>  (clone $query)->count(),
            // 'active_customers' =>  (clone $query)->where('is_active', true)->count(),
            // 'inactive_customers' =>  (clone $query)->where('is_active', false)->count(),
            // 'trashed_customers' =>  (clone $query)->onlyTrashed()->count(),
            // 'customers_with_balance' =>  (clone $query)->where('current_balance', '!=', 0)->count(),
            // 'customers_over_credit_limit' =>  (clone $query)->whereColumn('current_balance', '>', 'credit_limit')
            //     ->whereNotNull('credit_limit')->count(),
            'total_customer_balance' =>  (clone $query)->sum('current_balance'),
            // 'customers_by_type' =>  (clone $query)->with('customerType:id,name')
            //     ->selectRaw('customer_type_id, count(*) as count')
            //     ->groupBy('customer_type_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'customers_by_province' =>  (clone $query)->with('customerProvince:id,name')
            //     ->selectRaw('customer_province_id, count(*) as count')
            //     ->groupBy('customer_province_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'customers_by_zone' =>  (clone $query)->with('customerZone:id,name')
            //     ->selectRaw('customer_zone_id, count(*) as count')
            //     ->groupBy('customer_zone_id')
            //     ->having('count', '>', 0)
            //     ->get(),
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

    /**
     * Import customers from CSV/Excel file
     */
    public function import(CustomersImportRequest $request): JsonResponse
    {
        if(! RoleHelper::canAdmin()){
            return ApiResponse::customError('Only admins can import', 403); 
        }

        try {
            $file = $request->file('file');
            $skipDuplicates = $request->boolean('skip_duplicates', true);
            $updateExisting = $request->boolean('update_existing', true);

            // Create import instance
            $import = new CustomersImport($skipDuplicates, $updateExisting);

            // Process the import
            Excel::import($import, $file);

            // Get results
            $results = $import->getResults();

            // Determine response status
            if ($results['imported'] === 0 && $results['updated'] === 0) {
                Log::warning('No customers imported', [
                    'total_rows' => $results['total'],
                    'skipped' => $results['skipped'],
                    'errors' => $results['errors']
                ]);

                return ApiResponse::customError(
                    'No customers were imported. Please check the file format and data.',
                    422,
                    $results
                );
            }

            return ApiResponse::store(
                'Customers import completed successfully',
                $results
            );

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            Log::error('Customer Import Validation Failed', ['errors' => $errors]);

            return ApiResponse::customError('Import validation failed', 422, [
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Customer Import error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::customError(
                'Import failed: ' . $e->getMessage(),
                500,
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
        }
    }

    /**
     * Download sample Excel/CSV template for customer import
     */
    public function downloadTemplate(Request $request)
    {
        if(!RoleHelper::canAdmin()){
            return ApiResponse::customError('Only admins can perform this action', 403);
        }
        $format = $request->query('format', 'xlsx'); // Default to xlsx

        $headers = [
            'code',
            'name',
            'customer_type',
            'customer_group',
            'customer_province',
            'customer_zone',
            'starting_balance',
            'address',
            'city',
            'telephone',
            'mobile',
            'email',
            'url',
            'contact_name',
            'gps_coordinates',
            'mof_tax_number',
            'salesperson',
            'payment_term',
            'discount_percentage',
            'credit_limit',
            'price_list_inv_code',
            'price_list_inx_code',
            'notes',
            'created_at',
            'total_old_sales',
            'is_active'
        ];

        $sampleData = [
            [
                '',  // code - leave empty for auto-generation
                'Sample Customer Ltd',
                'Retailer',
                'Gold',
                'Beirut',
                'Central',
                5000,  // starting_balance - positive means customer owes us (debit), negative means we owe customer (credit)
                '123 Main Street',
                'Beirut',
                '+961 1 234567',
                '+961 70 123456',
                'info@samplecustomer.com',
                'https://www.samplecustomer.com',
                'John Doe',
                '33.8886,35.4955',
                'TAX123456',
                'EMP001',
                'Net 30',
                5,
                100000,
                'price list one',
                'price list two',
                'VIP Customer',
                '22-12-2025',
                0,
                true
            ]
        ];

        if ($format === 'csv') {
            // Generate CSV file
            $callback = function() use ($headers, $sampleData) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);

                foreach ($sampleData as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return response()->streamDownload($callback, 'customer_import_template.csv', [
                'Content-Type' => 'text/csv',
            ]);
        } else {
            // Generate Excel file using Laravel Excel
            return Excel::download(
                new class($headers, $sampleData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                    protected $headers;
                    protected $data;

                    public function __construct($headers, $data)
                    {
                        $this->headers = $headers;
                        $this->data = $data;
                    }

                    public function array(): array
                    {
                        return $this->data;
                    }

                    public function headings(): array
                    {
                        return $this->headers;
                    }
                },
                'customer_import_template.xlsx'
            );
        }
    }

    /**
     * Recalculate balance for all customers
     */
    public function recalculateBalances(Request $request): JsonResponse
    {
        // Only allow admin to recalculate balances
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can recalculate customer balances', 403);
        }

        // Increase time limit for large datasets
        set_time_limit(3600); // 1 hour

        // Get the statement controller instance
        $statementController = app(CustomerStatmentController::class);

        // Call the balance recalculation method
        $result = $statementController->processBalanceRecalculation();

        return ApiResponse::show(
            "Balance recalculation completed. {$result['updated_count']} customer(s) updated out of {$result['total_customers']} total customers.",
            $result
        );
    }

    public function customerQuery(Request $request)
    {
        $query = Customer::query()
            ->withCount('children')
            ->with([
                // 'parent:id,code,name',
                // 'customerType:id,name',
                // 'customerGroup:id,name',
                'priceListINV:id,code,description',
                'priceListINX:id,code,description',
                // 'customerProvince:id,name',
                // 'customerZone:id,name',
                'salesperson:id,code,name,department_id',
                // 'salesperson.department:id,name',
                // 'customerPaymentTerm:id,name,days',
                // 'createdBy:id,name',
                // 'updatedBy:id,name',
                // 'documents'
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

        if ($request->boolean('hide_zero_balance')) {
            $query->whereNotBetween('current_balance', [-1, 1]);
        }

        // Filter by customer group
        if ($request->has('customer_group_id')) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        // Filter by customer price lists
        if ($request->has('price_list_id_INV')) {
            $query->where('price_list_id_INV', $request->price_list_id_INV);
        }

        if ($request->has('price_list_id_INX')) {
            $query->where('price_list_id_INX', $request->price_list_id_INX);
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

        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
            }
        }

        return $query;

    }

 
}
