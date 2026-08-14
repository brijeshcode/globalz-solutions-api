<?php

namespace App\Http\Resources\Api\Customers;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customers\Customer
 *
 * @property float|int|string|null $sales_sum_total_usd Computed at query time (addSelect / loadSum), not a model column
 */
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
                    'current_balance' => $this->parent->current_balance ? (float) $this->parent->current_balance : 0,
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
            // 'opening_balance' =>  0,
            'current_balance' => $this->current_balance ? (float) $this->current_balance : 0,
            'balance_status' => $this->getBalanceStatusAttribute(),
            // 'current_balance' => $this->getCurrentBalanceAttribute(),
            'total_old_sales' => $this->total_old_sales,
            
            // Additional Information
            'address' => $this->address,
            'city' => $this->city,
            'telephone' => $this->telephone,
            'mobile' => $this->mobile,
            'url' => $this->url,
            'google_map' => $this->google_map,
            'email' => $this->email,
            'contact_name' => $this->contact_name,
            'gps_coordinates' => $this->gps_coordinates,
            'formatted_gps' => $this->getFormattedGpsAttribute(),
            'mof_tax_number' => $this->mof_tax_number,
            'price_list_id_INV' => $this->price_list_id_INV,
            'price_list_id_INX' => $this->price_list_id_INX,
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
            'price_list_INV' => $this->whenLoaded('priceListINV', function () {
                return $this->priceListINV ? [
                    'id' => $this->priceListINV->id,
                    'code' => $this->priceListINV->code,
                    'description' => $this->priceListINV->description,
                    'item_count' => $this->priceListINV->item_count,
                ] : null;
            }),
            'price_list_INX' => $this->whenLoaded('priceListINX', function () {
                return $this->priceListINX ? [
                    'id' => $this->priceListINX->id,
                    'code' => $this->priceListINX->code,
                    'description' => $this->priceListINX->description,
                    'item_count' => $this->priceListINX->item_count,
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
            'has_children' => $this->relationLoaded('children') ? $this->children->isNotEmpty() : $this->hasChildren(),
            'is_over_credit_limit' => $this->isOverCreditLimit(),
            'sales_count' => $this->sales_count ?? 0,
            'payment_count' => $this->customer_payments_count ?? $this->payment_count ?? 0,
            'return_count' => $this->customer_returns_count ?? $this->return_count ?? 0,
            'sales_total_usd' => $this->sales_sum_total_usd ? (float) $this->sales_sum_total_usd : 0,
            'last_invoice_date' => $this->last_invoice_date ?? null,
            'invoice_age' => isset($this->invoice_age) ? (int) $this->invoice_age : null,
            'last_payment_date' => $this->last_payment_date ?? null,
            'payment_age' => isset($this->payment_age) ? (int) $this->payment_age : null,

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
            'full_display_name' => $this->code . ' - ' . $this->name,
            
            // Credit status indicators
            'credit_status' => $this->getCreditStatusDisplay(),
            'balance_summary' => $this->getBalanceSummary(),

            // Documents
            'documents' => EmbeddedDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }

    /**
     * Get credit status display information
     *
     * @return array{status: string, message: string, severity: string}
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
     *
     * @return array{amount: float, formatted: string, type: string, display_class: string, icon: string}
     */
    private function getBalanceSummary(): array
    {
        $balance = (float) $this->current_balance;
        
        return [
            'amount' => (float) $balance,
            'formatted' => number_format($balance, 2),
            'type' => $balance > 0 ? 'credit' : ($balance < 0 ? 'debit' : 'balanced'),
            'display_class' => $balance > 0 ? 'text-success' : ($balance < 0 ? 'text-danger' : 'text-muted'),
            'icon' => $balance > 0 ? 'arrow-up' : ($balance < 0 ? 'arrow-down' : 'minus'),
        ];
    }
}
