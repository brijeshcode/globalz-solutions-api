<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogDetailResource extends JsonResource
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

            // Batch info
            'activity_log_id' => $this->activity_log_id,
            'batch_no' => $this->batch_no,

            // What changed
            'model' => $this->model,
            'model_id' => $this->model_id,
            'model_name' => class_basename($this->model),
            'event' => $this->event,

            // Changes
            'changes' => $this->formatChanges(),

            // Timestamp
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'timestamp_human' => $this->timestamp->diffForHumans(),
            'date' => $this->timestamp->format('M d, Y'),
            'time' => $this->timestamp->format('h:i A'),

            // User who made this change
            'changed_by' => [
                'id' => $this->changedBy?->id,
                'name' => $this->changedBy?->name ?? 'System',
                'email' => $this->changedBy?->email,
            ],
        ];
    }

    /**
     * Format the changes for display
     */
    protected function formatChanges(): array
    {
        $formatted = [];
        $old = $this->changes['old'] ?? [];
        $new = $this->changes['new'] ?? [];

        // For created events, only show new values
        if ($this->event === 'created') {
            foreach ($new as $field => $newValue) {
                $formatted[] = [
                    // 'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'old' => null,
                    'new' => $this->formatValue($field, $newValue),
                    'type' => 'added',
                ];
            }
        }
        // For deleted events, only show old values
        elseif ($this->event === 'deleted') {
            foreach ($old as $field => $oldValue) {
                $formatted[] = [
                    // 'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'old' => $this->formatValue($field, $oldValue),
                    'new' => null,
                    'type' => 'removed',
                ];
            }
        }
        // For updated events, show old -> new
        else {
            foreach ($new as $field => $newValue) {
                $oldValue = $old[$field] ?? null;

                $formatted[] = [
                    // 'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'old' => $this->formatValue($field, $oldValue),
                    'new' => $this->formatValue($field, $newValue),
                    'type' => 'modified',
                ];
            }
        }

        return $formatted;
    }

    /**
     * Get human-readable field label
     */
    protected function getFieldLabel(string $field): string
    {
        $labels = [
            'customer_id' => 'Customer',
            'salesperson_id' => 'Salesperson',
            'supplier_id' => 'Supplier',
            'warehouse_id' => 'Warehouse',
            'total_usd' => 'Total (USD)',
            'sub_total_usd' => 'Subtotal (USD)',
            'discount_amount_usd' => 'Discount (USD)',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'approve_note' => 'Approval Note',
            'client_po_number' => 'Client PO Number',
            'quantity' => 'Quantity',
            'price_usd' => 'Price (USD)',
            'item_id' => 'Item',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Format value for display
     */
    protected function formatValue(string $field, $value)
    {
        if (is_null($value)) {
            return null;
        }

        // Format decimal fields
        if (in_array($field, ['total', 'total_usd', 'sub_total', 'sub_total_usd', 'discount_amount', 'discount_amount_usd', 'total_profit', 'price_usd'])) {
            return number_format((float)$value, 4);
        }

        // Format date fields
        if (str_ends_with($field, '_at') && $value) {
            try {
                return \Carbon\Carbon::parse($value)->format('M d, Y h:i A');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }
}
