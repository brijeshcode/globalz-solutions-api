<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'original_name',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'file_extension',
        'title',
        'description',
        'document_type',
        'folder',
        'tags',
        'sort_order',
        'is_public',
        'is_featured',
        'metadata',
        'uploaded_by'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The attributes that should be appended to the model's array form.
     */
    protected $appends = [
        'file_size_human',
        'thumbnail_url',
        'download_url',
        'preview_url'
    ];

    /**
     * Get the parent documentable model.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to only include images.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    /**
     * Scope a query to only include documents (PDFs, Word docs).
     */
    public function scopeDocuments($query)
    {
        return $query->whereIn('mime_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);
    }

    /**
     * Scope a query to only include spreadsheets.
     */
    public function scopeSpreadsheets($query)
    {
        return $query->whereIn('mime_type', [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    /**
     * Scope a query to filter by document type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeByModule($query, $module)
    {
        return $query->where('documentable_type', $module);
    }

    /**
     * Scope a query to only include featured documents.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include public documents.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to search documents.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('original_name', 'LIKE', "%{$search}%")
              ->orWhere('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get human readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get thumbnail URL for the document.
     */
    public function getThumbnailUrlAttribute(): string
    {
        // For images, return a signed URL that doesn't require authentication
        if (str_starts_with($this->mime_type, 'image/')) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'documents.preview-signed',
                now()->addHours(24), // Valid for 24 hours
                ['document' => $this->id]
            );
        }
        
        // For PDFs, also use signed URL for preview
        if ($this->mime_type === 'application/pdf') {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'documents.preview-signed',
                now()->addHours(24), // Valid for 24 hours
                ['document' => $this->id]
            );
        }
        
        // Return default thumbnails based on file type for other files
        return match($this->file_extension) {
            'doc', 'docx' => '/images/file-types/word.png',
            'xls', 'xlsx' => '/images/file-types/excel.png',
            'ppt', 'pptx' => '/images/file-types/powerpoint.png',
            'zip', 'rar' => '/images/file-types/archive.png',
            'txt' => '/images/file-types/text.png',
            default => '/images/file-types/default.png'
        };
    }

    /**
     * Get preview URL for the document (for modal/full view).
     */
    public function getPreviewUrlAttribute(): string
    {
        // For images and PDFs, return signed URL for preview
        if ($this->isImage() || $this->isPdf()) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'documents.preview-signed',
                now()->addHours(24),
                ['document' => $this->id]
            );
        }
        
        // For other file types, return download URL
        return $this->download_url;
    }

    /**
     * Get download URL for the document.
     */
    public function getDownloadUrlAttribute(): string
    {
        return url("/api/documents/{$this->id}/download");
    }

    /**
     * Check if the document is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the document is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if the document is a Word document.
     */
    public function isWordDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);
    }

    /**
     * Check if the document is a spreadsheet.
     */
    public function isSpreadsheet(): bool
    {
        return in_array($this->mime_type, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    /**
     * Get the file category for UI grouping.
     */
    public function getFileCategory(): string
    {
        if ($this->isImage()) return 'image';
        if ($this->isPdf()) return 'pdf';
        if ($this->isWordDocument()) return 'document';
        if ($this->isSpreadsheet()) return 'spreadsheet';
        
        return 'other';
    }
}