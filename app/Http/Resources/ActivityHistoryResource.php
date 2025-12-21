<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityHistoryResource extends JsonResource
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
            'timestamp' => $this->created_at->format('Y-m-d H:i:s'),
            'timestamp_human' => $this->created_at->diffForHumans(),
            'date' => $this->created_at->format('M d, Y'),
            'time' => $this->created_at->format('h:i A'),

            'changed_by' => [
                'id' => $this->causer?->id,
                'name' => $this->causer?->name ?? 'System',
                'email' => $this->causer?->email,
            ],

            'changes' => $this->formatChanges(),

            // Additional metadata if stored
            'ip_address' => $this->properties['ip_address'] ?? null,
            'user_agent' => $this->properties['user_agent'] ?? null,
        ];
    }

    /**
     * Format the changes for display
     */
    protected function formatChanges(): array
    {
        $changes = [];
        $old = $this->properties['old'] ?? [];
        $new = $this->properties['attributes'] ?? [];

        foreach ($new as $field => $newValue) {
            $oldValue = $old[$field] ?? null;

            if ($oldValue != $newValue) {
                $changes[] = [
                    'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'old' => $this->formatValue($field, $oldValue),
                    'new' => $this->formatValue($field, $newValue),
                    'type' => $this->detectChangeType($oldValue, $newValue),
                ];
            }
        }

        return $changes;
    }

    /**
     * Get human-readable field label
     */
    protected function getFieldLabel(string $field): string
    {
        // Custom labels for specific fields
        $labels = [
            'customer_id' => 'Customer',
            'salesperson_id' => 'Salesperson',
            'warehouse_id' => 'Warehouse',
            'total_usd' => 'Total (USD)',
            'sub_total_usd' => 'Subtotal (USD)',
            'discount_amount_usd' => 'Discount (USD)',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'approve_note' => 'Approval Note',
            'client_po_number' => 'Client PO Number',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Format value for display
     */
    protected function formatValue(string $field, $value)
    {
        if (is_null($value)) {
            return '(empty)';
        }

        // Format decimal fields
        if (in_array($field, ['total', 'total_usd', 'sub_total', 'sub_total_usd', 'discount_amount', 'discount_amount_usd', 'total_profit'])) {
            return number_format((float)$value, 2);
        }

        // Format date fields
        if (in_array($field, ['approved_at']) && $value) {
            return \Carbon\Carbon::parse($value)->format('M d, Y h:i A');
        }

        return $value;
    }

    /**
     * Detect the type of change
     */
    protected function detectChangeType($old, $new): string
    {
        if (is_null($old)) return 'added';
        if (is_null($new)) return 'removed';
        return 'modified';
    }
}
