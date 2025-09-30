<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            
            // Main Information
            'code' => $this->code,
            'name' => $this->name,
            
            // Parent-Child Relationships
            'parent' => $this->whenLoaded('parent', function () {
                return $this->parent ? [
                    'id' => $this->parent->id,
                    'code' => $this->parent->code,
                    'name' => $this->parent->name,
                ] : null;
            }),
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'code' => $child->code,
                        'name' => $child->name,
                        'current_balance' => $child->current_balance ? (float) $child->current_balance : 0,
                        'is_active' => (bool) $child->is_active,
                    ];
                });
            }),
            
            // Classification
            'customer_type' => $this->whenLoaded('customerType', function () {
                return $this->customerType ? [
                    'id' => $this->customerType->id,
                    'name' => $this->customerType->name,
                ] : null;
            }),
            'customer_group' => $this->whenLoaded('customerGroup', function () {
                return $this->customerGroup ? [
                    'id' => $this->customerGroup->id,
                    'name' => $this->customerGroup->name,
                ] : null;
            }),
            'customer_province' => $this->whenLoaded('customerProvince', function () {
                return $this->customerProvince ? [
                    'id' => $this->customerProvince->id,
                    'name' => $this->customerProvince->name,
                ] : null;
            }),
            'customer_zone' => $this->whenLoaded('customerZone', function () {
                return $this->customerZone ? [
                    'id' => $this->customerZone->id,
                    'name' => $this->customerZone->name,
                ] : null;
            }),

            // Financial Information
            'opening_balance' =>  0,
            'current_balance' => $this->current_balance ? (float) $this->current_balance : 0,
            'balance_status' => $this->getBalanceStatusAttribute(),
            // 'current_balance' => $this->getCurrentBalanceAttribute(),

            // Additional Information
            'address' => $this->address,
            'city' => $this->city,
            'telephone' => $this->telephone,
            'mobile' => $this->mobile,
            'url' => $this->url,
            'email' => $this->email,
            'contact_name' => $this->contact_name,
            'gps_coordinates' => $this->gps_coordinates,
            'formatted_gps' => $this->getFormattedGpsAttribute(),
            'mof_tax_number' => $this->mof_tax_number,

            // Sales Information
            'salesperson' => $this->whenLoaded('salesperson', function () {
                return $this->salesperson ? [
                    'id' => $this->salesperson->id,
                    'code' => $this->salesperson->code,
                    'name' => $this->salesperson->name,
                    // 'department' => $this->salesperson->whenLoaded('department', function () {
                    //     return [
                    //         'id' => $this->salesperson->department->id,
                    //         'name' => $this->salesperson->department->name,
                    //     ];
                    // }),
                ] : null;
            }),
            'customer_payment_term' => $this->whenLoaded('customerPaymentTerm', function () {
                return $this->customerPaymentTerm ? [
                    'id' => $this->customerPaymentTerm->id,
                    'name' => $this->customerPaymentTerm->name,
                    'days' => $this->customerPaymentTerm->days,
                ] : null;
            }),
            'discount_percentage' => $this->discount_percentage ? (float) $this->discount_percentage : 0,
            'credit_limit' => $this->credit_limit ? (float) $this->credit_limit : null,

            // Other Information
            'notes' => $this->notes,

            // System Fields
            'is_active' => (bool) $this->is_active,

            // Computed Properties
            'has_parent' => $this->hasParent(),
            'has_children' => $this->hasChildren(),
            'is_over_credit_limit' => $this->isOverCreditLimit(),

            // Audit Information
            'created_by' => $this->whenLoaded('createdBy', function () {
                return $this->createdBy ? [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ] : null;
            }),
            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return $this->updatedBy ? [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ] : null;
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Display helpers for frontend
            'name' => $this->name,
            'full_display_name' => $this->code . ' - ' . $this->name,
            
            // Credit status indicators
            'credit_status' => $this->getCreditStatusDisplay(),
            'balance_summary' => $this->getBalanceSummary(),

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
        ];
    }

    /**
     * Get credit status display information
     */
    private function getCreditStatusDisplay(): array
    {
        $status = 'normal';
        $message = 'Within credit terms';
        $severity = 'success';

        if ($this->credit_limit) {
            if ($this->current_balance > $this->credit_limit) {
                $status = 'over_limit';
                $message = 'Over credit limit by ' . number_format($this->current_balance - $this->credit_limit, 2);
                $severity = 'danger';
            } elseif ($this->current_balance > ($this->credit_limit * 0.9)) {
                $status = 'near_limit';
                $message = 'Near credit limit';
                $severity = 'warning';
            }
        }

        return [
            'status' => $status,
            'message' => $message,
            'severity' => $severity,
        ];
    }

    /**
     * Get balance summary for display
     */
    private function getBalanceSummary(): array
    {
        $balance = $this->current_balance ?? 0;
        
        return [
            'amount' => (float) $balance,
            'formatted' => number_format($balance, 2),
            'type' => $balance > 0 ? 'credit' : ($balance < 0 ? 'debit' : 'balanced'),
            'display_class' => $balance > 0 ? 'text-success' : ($balance < 0 ? 'text-danger' : 'text-muted'),
            'icon' => $balance > 0 ? 'arrow-up' : ($balance < 0 ? 'arrow-down' : 'minus'),
        ];
    }
}
