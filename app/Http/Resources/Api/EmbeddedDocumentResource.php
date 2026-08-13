<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight document representation embedded inside parent resources
 * (purchases, expenses, customers, items, suppliers, etc.). Intentionally
 * trimmed to the essentials (file name, size, type and access URLs); the
 * nested {@see DocumentResource} is the richer representation used on the
 * standalone document endpoints.
 *
 * @mixin \App\Models\Document
 */
class EmbeddedDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'file_extension' => $this->file_extension,
            'title' => $this->title,
            'file_size_human' => $this->file_size_human,
            'thumbnail_url' => $this->thumbnail_url,
            'download_url' => $this->download_url,
            'preview_url' => $this->preview_url,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
 
        ];

        
        // return [
        //         'id' => $this->id,
        //         'documentable_type' => $this->documentable_type,
        //         // 'documentable_id' => $this->documentable_id,
        //         'original_name' => $this->original_name,
        //         'file_name' => $this->file_name,
        //         'file_path' => $this->file_path,
        //         'file_size' => $this->file_size,
        //         'mime_type' => $this->mime_type,
        //         'file_extension' => $this->file_extension,
        //         'title' => $this->title,
        //         'description' => $this->description,
        //         'document_type' => $this->document_type,
        //         'folder' => $this->folder,
        //         'tags' => $this->tags,
        //         'sort_order' => $this->sort_order,
        //         'is_public' => $this->is_public,
        //         'is_featured' => $this->is_featured,
        //         'metadata' => $this->metadata,
        //         'uploaded_by' => $this->uploaded_by,
        //         // Appended attributes from Document model
        //         'file_size_human' => $this->file_size_human,
        //         'thumbnail_url' => $this->thumbnail_url,
        //         'download_url' => $this->download_url,
        //         'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        //         'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        //         'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        //     ];

        // return [
        //         'id' => $document->id,
        //         'documentable_type' => $document->documentable_type,
        //         'documentable_id' => $document->documentable_id,
        //         'original_name' => $document->original_name,
        //         'file_name' => $document->file_name,
        //         'file_path' => $document->file_path,
        //         'file_size' => $document->file_size,
        //         'mime_type' => $document->mime_type,
        //         'file_extension' => $document->file_extension,
        //         'title' => $document->title,
        //         'description' => $document->description,
        //         'document_type' => $document->document_type,
        //         'folder' => $document->folder,
        //         'tags' => $document->tags,
        //         'sort_order' => $document->sort_order,
        //         'is_public' => $document->is_public,
        //         'is_featured' => $document->is_featured,
        //         'metadata' => $document->metadata,
        //         'uploaded_by' => $document->uploaded_by,
        //         // Appended attributes from Document model
        //         'file_size_human' => $document->file_size_human,
        //         'thumbnail_url' => $document->thumbnail_url,
        //         'download_url' => $document->download_url,
        //         'created_at' => $document->created_at?->format('Y-m-d H:i:s'),
        //         'updated_at' => $document->updated_at?->format('Y-m-d H:i:s'),
        //         'deleted_at' => $document->deleted_at?->format('Y-m-d H:i:s'),
        //     ];
    }
}
