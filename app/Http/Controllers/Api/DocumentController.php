<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DocumentStoreRequest;
use App\Http\Requests\Api\DocumentUpdateRequest;
use App\Http\Resources\Api\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['documentable', 'uploadedBy']);

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate results
        $perPage = $request->get('per_page', 20);
        $documents = $query->paginate($perPage);

        return ApiResponse::paginated(
            'Documents retrieved successfully',
            $documents,
            DocumentResource::class
        );
    }

    /**
     * Store a newly created document.
     */
    public function store(DocumentStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // Generate filename with model type, ID, date, and original name
            $extension = $file->getClientOriginalExtension();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            // Clean the original filename (remove special characters, spaces)
            $cleanOriginalName = preg_replace('/[^A-Za-z0-9\-_]/', '-', $originalName);
            $cleanOriginalName = preg_replace('/-+/', '-', $cleanOriginalName); // Remove multiple dashes
            $cleanOriginalName = trim($cleanOriginalName, '-'); // Remove leading/trailing dashes
            
            // Get model information
            $documentableType = $validated['documentable_type'] ?? 'general';
            $modelName = strtolower(class_basename($documentableType));
            $modelId = $validated['documentable_id'] ?? 'new';
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            
            // Generate filename: modeltype-id-date-time-originalname.extension
            $fileName = "{$modelName}-{$modelId}-{$currentDate}-{$currentTime}-{$cleanOriginalName}.{$extension}";
            
            // Determine storage path
            $basePath = $modelName . '/documents';
            
            if (!empty($validated['folder'])) {
                $basePath .= '/' . $validated['folder'];
            }
            
            $basePath .= '/' . date('Y/m');
            
            // Store the file
            $filePath = $file->storeAs($basePath, $fileName, 'public');
            
            // Prepare document data
            $documentData = array_merge($validated, [
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_extension' => $extension,
                'uploaded_by' => Auth::id(),
            ]);
            
            unset($documentData['file']); // Remove file from data
            
            $document = Document::create($documentData);
            
            return ApiResponse::store(
                'Document uploaded successfully',
                new DocumentResource($document)
            );
        }
        
        return ApiResponse::customError('No file provided', 400);
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document): JsonResponse
    {
        $document->load(['documentable', 'uploadedBy']);
        
        return ApiResponse::show(
            'Document retrieved successfully',
            new DocumentResource($document)
        );
    }

    /**
     * Update the specified document.
     */
    public function update(DocumentUpdateRequest $request, Document $document): JsonResponse
    {
        $validated = $request->validated();
        
        // If a new file is uploaded, handle file replacement
        if ($request->hasFile('file')) {
            // Delete old file
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            // Upload new file
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            // Clean the original filename (remove special characters, spaces)
            $cleanOriginalName = preg_replace('/[^A-Za-z0-9\-_]/', '-', $originalName);
            $cleanOriginalName = preg_replace('/-+/', '-', $cleanOriginalName); // Remove multiple dashes
            $cleanOriginalName = trim($cleanOriginalName, '-'); // Remove leading/trailing dashes
            
            // Get model information
            $documentableType = $document->documentable_type;
            $modelName = strtolower(class_basename($documentableType));
            $modelId = $document->documentable_id;
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            
            // Generate filename: modeltype-id-date-time-originalname.extension
            $fileName = "{$modelName}-{$modelId}-{$currentDate}-{$currentTime}-{$cleanOriginalName}.{$extension}";
            
            // Use existing folder structure or provided folder
            $basePath = dirname($document->file_path);
            if (!empty($validated['folder'])) {
                $documentableType = $document->documentable_type;
                $modelName = class_basename($documentableType);
                $basePath = strtolower($modelName) . '/documents/' . $validated['folder'] . '/' . date('Y/m');
            }
            
            $filePath = $file->storeAs($basePath, $fileName, 'public');
            
            // Update file-related fields
            $validated = array_merge($validated, [
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_extension' => $extension,
            ]);
            
            unset($validated['file']);
        }
        
        $document->update($validated);
        
        return ApiResponse::update(
            'Document updated successfully',
            new DocumentResource($document->fresh(['documentable', 'uploadedBy']))
        );
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy(Document $document): JsonResponse
    {
        // Soft delete - only remove database record, keep physical file
        $document->delete();
        
        return ApiResponse::delete('Document deleted successfully (file preserved in system)');
    }

    /**
     * Download the specified document.
     */
    public function download(Document $document): BinaryFileResponse
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }
        
        $filePath = Storage::disk('public')->path($document->file_path);
        
        return response()->download($filePath, $document->original_name);
    }

    /**
     * Preview the specified document (show inline).
     */
    public function preview(Document $document): \Symfony\Component\HttpFoundation\Response
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }
        
        $filePath = Storage::disk('public')->path($document->file_path);
        
        return response()->file($filePath, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"'
        ]);
    }

    /**
     * Generate a temporary signed URL for document preview.
     */
    public function getPreviewUrl(Document $document): JsonResponse
    {
        // Generate a signed URL that's valid for 1 hour
        $signedUrl = URL::temporarySignedRoute(
            'documents.preview-signed',
            now()->addHour(),
            ['document' => $document->id]
        );

        return ApiResponse::show(
            'Preview URL generated successfully',
            ['preview_url' => $signedUrl]
        );
    }

    /**
     * Preview document via signed URL (no auth required).
     */
    public function previewSigned(Request $request, Document $document): \Symfony\Component\HttpFoundation\Response
    {
        // Check if the signed URL is valid
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired preview link');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }
        
        $filePath = Storage::disk('public')->path($document->file_path);
        
        return response()->file($filePath, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"'
        ]);
    }

    /**
     * Get document statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_documents' => Document::count(),
            'total_size' => Document::sum('file_size'),
            'total_size_human' => $this->formatBytes(Document::sum('file_size')),
            'by_type' => [
                'images' => Document::images()->count(),
                'documents' => Document::documents()->count(),
                'spreadsheets' => Document::spreadsheets()->count(),
                'others' => Document::whereNotIn('mime_type', [
                    'image/jpeg', 'image/png', 'image/gif', 'image/bmp',
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ])->count(),
            ],
            'by_module' => Document::selectRaw('documentable_type, COUNT(*) as count')
                ->groupBy('documentable_type')
                ->pluck('count', 'documentable_type')
                ->toArray(),
            'by_document_type' => Document::selectRaw('document_type, COUNT(*) as count')
                ->whereNotNull('document_type')
                ->groupBy('document_type')
                ->pluck('count', 'document_type')
                ->toArray(),
            'recent_uploads' => DocumentResource::collection(
                Document::with(['documentable', 'uploadedBy'])
                    ->latest()
                    ->take(10)
                    ->get()
            ),
            'featured_documents' => DocumentResource::collection(
                Document::with(['documentable', 'uploadedBy'])
                    ->featured()
                    ->take(5)
                    ->get()
            ),
        ];
        
        return ApiResponse::show('Document statistics retrieved successfully', $stats);
    }

    /**
     * Get documents for a specific model.
     */
    public function getModelDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'documentable_type' => 'required|string',
            'documentable_id' => 'required|integer',
        ]);
        
        $query = Document::where('documentable_type', $request->documentable_type)
                        ->where('documentable_id', $request->documentable_id)
                        ->with(['uploadedBy']);
        
        // Apply additional filters
        $this->applyFilters($query, $request);
        
        // Apply sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection)
              ->orderBy('created_at', 'desc');
        
        $documents = $query->get();
        
        return ApiResponse::show(
            'Model documents retrieved successfully',
            DocumentResource::collection($documents)
        );
    }

    /**
     * Bulk delete documents.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
            'force_delete' => 'nullable|boolean', // Allow option for force delete
        ]);
        
        $documents = Document::whereIn('id', $request->document_ids)->get();
        $deletedCount = 0;
        $forceDelete = $request->boolean('force_delete', false);
        
        foreach ($documents as $document) {
            if ($forceDelete) {
                // Force delete - remove physical file and database record permanently
                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                
                if ($document->forceDelete()) {
                    $deletedCount++;
                }
            } else {
                // Soft delete - only remove database record, keep physical file
                if ($document->delete()) {
                    $deletedCount++;
                }
            }
        }
        
        $deleteType = $forceDelete ? 'permanently deleted' : 'deleted (files preserved)';
        return ApiResponse::delete(
            "Successfully {$deleteType} {$deletedCount} documents",
            ['deleted_count' => $deletedCount, 'force_delete' => $forceDelete]
        );
    }

    /**
     * Restore a soft-deleted document.
     */
    public function restore(int $id): JsonResponse
    {
        $document = Document::withTrashed()->find($id);
        
        if (!$document) {
            return ApiResponse::customError('Document not found', 404);
        }
        
        if (!$document->trashed()) {
            return ApiResponse::customError('Document is not deleted', 400);
        }
        
        $document->restore();
        
        return ApiResponse::update(
            'Document restored successfully',
            new DocumentResource($document->fresh(['documentable', 'uploadedBy']))
        );
    }

    /**
     * Permanently delete a document and its physical file.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $document = Document::withTrashed()->find($id);
        
        if (!$document) {
            return ApiResponse::notFound('Document not found', 404);
        }
        
        // Delete physical file
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
        
        // Permanently delete from database
        $document->forceDelete();
        
        return ApiResponse::delete('Document permanently deleted (file removed from system)');
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters($query, Request $request): void
    {
        // Filter by file type category
        if ($request->filled('file_category')) {
            switch ($request->file_category) {
                case 'images':
                    $query->images();
                    break;
                case 'documents':
                    $query->documents();
                    break;
                case 'spreadsheets':
                    $query->spreadsheets();
                    break;
            }
        }
        
        // Filter by specific MIME type
        if ($request->filled('mime_type')) {
            $query->where('mime_type', $request->mime_type);
        }
        
        // Filter by document type
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        
        // Filter by folder
        if ($request->filled('folder')) {
            $query->where('folder', $request->folder);
        }
        
        // Filter by module (documentable_type)
        if ($request->filled('module')) {
            $query->where('documentable_type', $request->module);
        }
        
        // Filter by specific model instance
        if ($request->filled('documentable_id')) {
            $query->where('documentable_id', $request->documentable_id);
        }
        
        // Filter by featured status
        if ($request->filled('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }
        
        // Filter by public status
        if ($request->filled('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }
        
        // Filter by uploaded user
        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->uploaded_by);
        }
        
        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Filter by file size range
        if ($request->filled('size_from')) {
            $query->where('file_size', '>=', $request->size_from);
        }
        
        if ($request->filled('size_to')) {
            $query->where('file_size', '<=', $request->size_to);
        }
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}