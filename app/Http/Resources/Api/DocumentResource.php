<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            
            // Polymorphic relationship info
            'documentable' => [
                'type' => $this->documentable_type,
                'id' => $this->documentable_id,
                'model_name' => class_basename($this->documentable_type),
                'model_data' => $this->whenLoaded('documentable', function () {
                    return $this->formatDocumentableData();
                }),
            ],
            
            // File information
            'file' => [
                'original_name' => $this->original_name,
                'file_name' => $this->file_name,
                'file_path' => $this->file_path,
                'file_size' => $this->file_size,
                'file_size_human' => $this->file_size_human,
                'mime_type' => $this->mime_type,
                'file_extension' => $this->file_extension,
                'file_category' => $this->getFileCategory(),
            ],
            
            // Document metadata
            'metadata' => [
                'title' => $this->title,
                'description' => $this->description,
                'document_type' => $this->document_type,
                'folder' => $this->folder,
                'tags' => $this->tags ?? [],
                'sort_order' => $this->sort_order,
                'custom_metadata' => $this->metadata ?? [],
            ],
            
            // Access control
            'access' => [
                'is_public' => $this->is_public,
                'is_featured' => $this->is_featured,
            ],
            
            // URLs and actions
            'urls' => [
                'thumbnail' => $this->thumbnail_url,
                'download' => $this->download_url,
                'view' => $this->when(
                    $this->isImage() || $this->isPdf(),
                    route('documents.download', $this->id)
                ),
            ],
            
            // File type helpers
            'file_types' => [
                'is_image' => $this->isImage(),
                'is_pdf' => $this->isPdf(),
                'is_word_document' => $this->isWordDocument(),
                'is_spreadsheet' => $this->isSpreadsheet(),
            ],
            
            // User information
            'uploaded_by' => $this->whenLoaded('uploadedBy', function () {
                return [
                    'id' => $this->uploadedBy->id,
                    'name' => $this->uploadedBy->name,
                    'email' => $this->uploadedBy->email,
                ];
            }),
            
            // Timestamps
            'timestamps' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
                'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
                'created_at_human' => $this->created_at?->diffForHumans(),
                'updated_at_human' => $this->updated_at?->diffForHumans(),
            ],
        ];
    }

    /**
     * Format the documentable model data based on its type.
     */
    protected function formatDocumentableData(): array
    {
        if (!$this->documentable) {
            return [];
        }

        $model = $this->documentable;
        $modelName = class_basename($this->documentable_type);

        // Common fields that most models might have
        $commonData = [
            'id' => $model->id,
        ];

        // Add name field if it exists
        if (isset($model->name)) {
            $commonData['name'] = $model->name;
        }

        // Add title field if it exists (for models that use title instead of name)
        if (isset($model->title)) {
            $commonData['title'] = $model->title;
        }

        // Add code field if it exists
        if (isset($model->code)) {
            $commonData['code'] = $model->code;
        }

        // Add specific fields based on model type
        switch ($modelName) {
            case 'Supplier':
                return array_merge($commonData, [
                    'type' => 'supplier',
                    'email' => $model->email ?? null,
                    'phone' => $model->phone ?? null,
                    'country' => $model->country?->name ?? null,
                    'supplier_type' => $model->supplierType?->name ?? null,
                    'is_active' => $model->is_active ?? null,
                ]);

            case 'Customer':
                return array_merge($commonData, [
                    'type' => 'customer',
                    'email' => $model->email ?? null,
                    'phone' => $model->phone ?? null,
                    'is_active' => $model->is_active ?? null,
                ]);

            case 'Product':
                return array_merge($commonData, [
                    'type' => 'product',
                    'sku' => $model->sku ?? null,
                    'category' => $model->category?->name ?? null,
                    'brand' => $model->brand?->name ?? null,
                    'is_active' => $model->is_active ?? null,
                ]);

            case 'Warehouse':
                return array_merge($commonData, [
                    'type' => 'warehouse',
                    'location' => $model->location ?? null,
                    'is_active' => $model->is_active ?? null,
                ]);

            case 'User':
                return array_merge($commonData, [
                    'type' => 'user',
                    'email' => $model->email ?? null,
                    'role' => $model->role ?? null,
                ]);

            default:
                // For unknown models, return common data
                return array_merge($commonData, [
                    'type' => strtolower($modelName),
                ]);
        }
    }

    /**
     * Get additional data when the resource is used in a collection.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'document_types_available' => [
                    'contract', 'invoice', 'certificate', 'photo', 'manual',
                    'specification', 'report', 'presentation', 'legal', 'warranty',
                    'receipt', 'statement', 'agreement', 'proposal', 'quote',
                    'order', 'delivery', 'tax', 'insurance', 'license',
                    'permit', 'registration', 'identification', 'passport',
                    'visa', 'medical', 'educational', 'financial', 'technical',
                    'marketing', 'administrative', 'personal', 'other'
                ],
                'file_categories' => [
                    'image' => 'Images (JPG, PNG, GIF, etc.)',
                    'pdf' => 'PDF Documents',
                    'document' => 'Word Documents',
                    'spreadsheet' => 'Spreadsheets',
                    'other' => 'Other Files'
                ],
                'max_file_size' => '10MB',
                'allowed_extensions' => [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg',
                    'txt', 'csv', 'zip', 'rar'
                ],
            ],
        ];
    }
}