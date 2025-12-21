<?php

namespace App\Traits;

use App\Models\Document;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

trait HasDocuments
{
    /**
     * Get all documents for this model
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get active (non-deleted) documents
     */
    public function activeDocuments(): MorphMany
    {
        return $this->documents()->whereNull('deleted_at');
    }

    /**
     * Get documents by type
     */
    public function documentsByType(string $type): MorphMany
    {
        return $this->documents()->where('document_type', $type);
    }

    /**
     * Create documents from uploaded files
     */
    public function createDocuments(array $files, array $metadata = []): Collection
    {
        $uploadedDocuments = new Collection();

        foreach ($files as $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }

            $document = $this->uploadSingleFile($file, $metadata);
            if ($document) {
                $uploadedDocuments->push($document);
            }
        }

        return $uploadedDocuments;
    }

    /**
     * Update documents - add new files (old documents remain)
     */
    public function updateDocuments(array $files, array $metadata = []): Collection
    {
        return $this->createDocuments($files, $metadata);
    }

    /**
     * Soft delete documents
     */
    public function deleteDocuments(?array $documentIds = null): bool
    {
        $query = $this->documents();

        if ($documentIds) {
            $query->whereIn('id', $documentIds);
        }

        return $query->delete();
    }

    /**
     * Restore soft deleted documents
     */
    public function restoreDocuments(?array $documentIds = null): bool
    {
        $query = $this->documents()->onlyTrashed();

        if ($documentIds) {
            $query->whereIn('id', $documentIds);
        }

        return $query->restore();
    }

    /**
     * Permanently delete documents
     */
    public function forceDeleteDocuments(?array $documentIds = null): bool
    {
        $query = $this->documents()->withTrashed();

        if ($documentIds) {
            $query->whereIn('id', $documentIds);
        }

        $documents = $query->get();

        // Delete physical files
        foreach ($documents as $document) {
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
        }

        return $query->forceDelete();
    }

    /**
     * Get documents with filters
     */
    public function getDocuments(array $filters = []): Collection
    {
        $query = $this->documents();

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('document_type', $filters['type']);
        }

        if (!empty($filters['title'])) {
            $query->where('title', 'like', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        return $query->get();
    }

    /**
     * Static method to get documents by documentable type with filters
     */
    public static function getByType(string $documentableType, array $filters = []): Collection
    {
        $query = Document::where('documentable_type', $documentableType);

        // Apply filters
        if (!empty($filters['documentable_id'])) {
            $query->where('documentable_id', $filters['documentable_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('document_type', $filters['type']);
        }

        if (!empty($filters['title'])) {
            $query->where('title', 'like', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        return $query->get();
    }

    /**
     * Check if model has documents
     */
    public function hasDocuments(): bool
    {
        return $this->documents()->exists();
    }

    /**
     * Get documents count
     */
    public function getDocumentsCount(): int
    {
        return $this->documents()->count();
    }

    /**
     * Get allowed document file extensions for this model
     */
    public function getAllowedDocumentExtensions(): array
    {
        return [
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'txt', 'csv', 'zip', 'rar'
        ];
    }

    /**
     * Get maximum file size for document uploads (in bytes)
     */
    public function getMaxDocumentFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB default
    }

    /**
     * Get maximum number of documents allowed per model
     */
    public function getMaxDocumentsCount(): int
    {
        return 50; // 50 documents default
    }

    /**
     * Validate uploaded file against model constraints
     */
    public function validateDocumentFile(UploadedFile $file): array
    {
        $errors = [];
        
        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_map('strtolower', $this->getAllowedDocumentExtensions());
        
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "File extension '{$extension}' is not allowed. Allowed extensions: " . implode(', ', $allowedExtensions);
        }
        
        // Check file size
        if ($file->getSize() > $this->getMaxDocumentFileSize()) {
            $maxSizeMB = round($this->getMaxDocumentFileSize() / (1024 * 1024), 2);
            $fileSizeMB = round($file->getSize() / (1024 * 1024), 2);
            $errors[] = "File size ({$fileSizeMB}MB) exceeds maximum allowed size ({$maxSizeMB}MB)";
        }
        
        // Check documents count
        if ($this->getDocumentsCount() >= $this->getMaxDocumentsCount()) {
            $errors[] = "Maximum number of documents ({$this->getMaxDocumentsCount()}) reached";
        }
        
        return $errors;
    }

    /**
     * Private method to handle single file upload
     */
    private function uploadSingleFile(UploadedFile $file, array $metadata = []): ?Document
    {
        try {
            // Validate file before upload
            $validationErrors = $this->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                logger()->warning('Document upload validation failed', [
                    'errors' => $validationErrors,
                    'file' => $file->getClientOriginalName(),
                    'model' => get_class($this),
                    'model_id' => $this->id
                ]);
                return null;
            }

            // Generate filename with model type, ID, date, and original name
            $extension = $file->getClientOriginalExtension();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            // Clean the original filename (remove special characters, spaces)
            $cleanOriginalName = preg_replace('/[^A-Za-z0-9\-_]/', '-', $originalName);
            $cleanOriginalName = preg_replace('/-+/', '-', $cleanOriginalName); // Remove multiple dashes
            $cleanOriginalName = trim($cleanOriginalName, '-'); // Remove leading/trailing dashes
            
            // Get model information
            $modelName = strtolower(class_basename($this));
            $modelId = $this->id ?? 'new';
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            
            // Generate filename: modeltype-id-date-time-originalname.extension
            $filename = "{$modelName}-{$modelId}-{$currentDate}-{$currentTime}-{$cleanOriginalName}.{$extension}";
            
            // Get plural module name for folder organization
            $moduleName = $this->getModuleFolderName();

            // Determine storage path with tenant awareness: documents/[tenant]/YYYY/MM/module-name
            $basePath = $this->getTenantAwareBasePath($moduleName);

            // Store file
            $filePath = $file->storeAs($basePath, $filename, 'public');

            if (!$filePath) {
                return null;
            }

            // Create document record
            $documentData = [
                'documentable_type' => get_class($this),
                'documentable_id' => $this->id,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $filename,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $metadata['description'] ?? null,
                'document_type' => $metadata['type'] ?? 'default',
                'folder' => $metadata['folder'] ?? null,
                'tags' => $metadata['tags'] ?? null,
                'is_public' => $metadata['is_public'] ?? false,
                'is_featured' => $metadata['is_featured'] ?? false,
                'uploaded_by' => Auth::id(),
                'metadata' => $metadata['metadata'] ?? null,
            ];

            return Document::create($documentData);

        } catch (\Exception $e) {
            // Log error but don't throw to prevent breaking the main operation
            logger()->error('Document upload failed: ' . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'model' => get_class($this),
                'model_id' => $this->id
            ]);

            return null;
        }
    }

    /**
     * Get the module folder name for file organization
     * Override this method in models that need custom folder names
     */
    protected function getModuleFolderName(): string
    {
        $className = class_basename($this);

        // Convert class name to plural form for folder
        $folderName = strtolower($className);

        // Handle common plural forms
        if (substr($folderName, -1) === 'y') {
            $folderName = substr($folderName, 0, -1) . 'ies';
        } elseif (in_array(substr($folderName, -1), ['s', 'x', 'z']) ||
                  in_array(substr($folderName, -2), ['ch', 'sh'])) {
            $folderName .= 'es';
        } else {
            $folderName .= 's';
        }

        return $folderName;
    }

    /**
     * Get tenant-aware base path for document storage
     */
    protected function getTenantAwareBasePath(string $moduleName): string
    {
        $currentTenant = Tenant::current();

        // If we have a current tenant, include tenant folder in path
        if ($currentTenant) {
            // Use tenant_key as folder identifier
            $tenantIdentifier = $currentTenant->tenant_key;
            return 'documents/' . $tenantIdentifier . '/' . date('Y/m') . '/' . $moduleName;
        }

        // Fallback to non-tenant path (landlord context)
        return 'documents/' . date('Y/m') . '/' . $moduleName;
    }
}