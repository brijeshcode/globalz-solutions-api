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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $this->handleAttachments($request->file('attachments'));
        } elseif ($request->has('attachments') && is_array($request->attachments)) {
            // Handle attachments as array of file paths or base64 strings
            $data['attachments'] = $this->processAttachmentPaths($request->attachments);
        }

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
        $data = $request->validated();

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            // Delete old attachments if new ones are uploaded
            if ($supplier->attachments) {
                $this->deleteAttachments($supplier->attachments);
            }
            $data['attachments'] = $this->handleAttachments($request->file('attachments'));
        } elseif ($request->has('attachments') && is_array($request->attachments)) {
            // Handle attachments as array of file paths or keep existing ones
            $data['attachments'] = $this->processAttachmentPaths($request->attachments, $supplier->attachments);
        } elseif ($request->has('attachments') && $request->attachments === null) {
            // Explicitly remove all attachments
            if ($supplier->attachments) {
                $this->deleteAttachments($supplier->attachments);
            }
            $data['attachments'] = null;
        }

        $supplier->update($data);
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
        
        // Delete associated attachments when permanently deleting supplier
        if ($supplier->attachments) {
            $this->deleteAttachments($supplier->attachments);
        }
        
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

    /**
     * Upload attachments for supplier
     */
    public function uploadAttachments(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls|max:10240' // 10MB max per file
        ]);

        $newAttachments = $this->handleAttachments($request->file('attachments'));
        
        // Merge with existing attachments
        $existingAttachments = $supplier->attachments ?? [];
        $allAttachments = array_merge($existingAttachments, $newAttachments);
        
        $supplier->update(['attachments' => $allAttachments]);
        
        return ApiResponse::update(
            'Attachments uploaded successfully',
            ['attachments' => $allAttachments]
        );
    }

    /**
     * Delete specific attachment
     */
    public function deleteAttachment(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate([
            'attachment_path' => 'required|string'
        ]);

        $attachments = $supplier->attachments ?? [];
        $attachmentToDelete = $request->attachment_path;
        
        // Remove from array
        $updatedAttachments = array_filter($attachments, function($attachment) use ($attachmentToDelete) {
            return $attachment !== $attachmentToDelete;
        });
        
        // Delete physical file
        if (Storage::disk('public')->exists($attachmentToDelete)) {
            Storage::disk('public')->delete($attachmentToDelete);
        }
        
        $supplier->update(['attachments' => array_values($updatedAttachments)]);
        
        return ApiResponse::update(
            'Attachment deleted successfully',
            ['attachments' => array_values($updatedAttachments)]
        );
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(Request $request, Supplier $supplier)
    {
        $request->validate([
            'attachment_path' => 'required|string'
        ]);

        $attachmentPath = $request->attachment_path;
        
        // Verify attachment belongs to this supplier
        if (!in_array($attachmentPath, $supplier->attachments ?? [])) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }
        
        if (!Storage::disk('public')->exists($attachmentPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        return response()->download(Storage::disk('public')->path($attachmentPath));
    }

    /**
     * Handle file uploads and return array of file paths
     */
    private function handleAttachments(array $files): array
    {
        $attachmentPaths = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('suppliers/attachments', $filename, 'public');
                $attachmentPaths[] = $path;
            }
        }
        
        return $attachmentPaths;
    }

    /**
     * Process attachment paths from request (handles existing paths and new uploads)
     */
    private function processAttachmentPaths(array $attachments, ?array $existingAttachments = null): array
    {
        $processedPaths = [];
        
        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                // If it's a string, it could be an existing path or base64 data
                if (strpos($attachment, 'data:') === 0) {
                    // Handle base64 encoded file
                    $processedPaths[] = $this->handleBase64Upload($attachment);
                } else {
                    // Existing file path
                    $processedPaths[] = $attachment;
                }
            }
        }
        
        return $processedPaths;
    }

    /**
     * Handle base64 file upload
     */
    private function handleBase64Upload(string $base64Data): string
    {
        // Extract file info from base64 string
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $data = base64_decode($matches[2]);
            
            // Determine file extension from mime type
            $extensions = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            ];
            
            $extension = $extensions[$mimeType] ?? 'txt';
            $filename = time() . '_' . Str::random(10) . '.' . $extension;
            $path = 'suppliers/attachments/' . $filename;
            
            Storage::disk('public')->put($path, $data);
            
            return $path;
        }
        
        throw new \InvalidArgumentException('Invalid base64 file data');
    }

    /**
     * Delete attachment files from storage
     */
    private function deleteAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (Storage::disk('public')->exists($attachment)) {
                Storage::disk('public')->delete($attachment);
            }
        }
    }
}