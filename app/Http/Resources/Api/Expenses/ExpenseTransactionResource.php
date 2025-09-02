<?php

namespace App\Http\Resources\Api\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseTransactionResource extends JsonResource
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
            'date' => $this->date?->format('Y-m-d'),
            'code' => $this->code,
            'subject' => $this->subject,
            'amount' => $this->amount,
            'order_number' => $this->order_number,
            'check_number' => $this->check_number,
            'bank_ref_number' => $this->bank_ref_number,
            'note' => $this->note,

            'expense_category' => $this->whenLoaded('expenseCategory', function () {
                return [
                    'id' => $this->expenseCategory->id,
                    'name' => $this->expenseCategory->name,
                ];
            }),

            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                ];
            }),

            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],

            // Documents
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'documentable_type' => $document->documentable_type,
                        'documentable_id' => $document->documentable_id,
                        'original_name' => $document->original_name,
                        'file_name' => $document->file_name,
                        'file_path' => $document->file_path,
                        'file_size' => $document->file_size,
                        'mime_type' => $document->mime_type,
                        'file_extension' => $document->file_extension,
                        'title' => $document->title,
                        'description' => $document->description,
                        'document_type' => $document->document_type,
                        'folder' => $document->folder,
                        'tags' => $document->tags,
                        'sort_order' => $document->sort_order,
                        'is_public' => $document->is_public,
                        'is_featured' => $document->is_featured,
                        'metadata' => $document->metadata,
                        'uploaded_by' => $document->uploaded_by,
                        // Appended attributes from Document model
                        'file_size_human' => $document->file_size_human,
                        'thumbnail_url' => $document->thumbnail_url,
                        'download_url' => $document->download_url,
                        'preview_url' => $document->preview_url,
                        'created_at' => $document->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $document->updated_at?->format('Y-m-d H:i:s'),
                        'deleted_at' => $document->deleted_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
