<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * This resource represents a single batch with parent and child changes grouped
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this->resource is a collection of ActivityLogDetail items for this batch
        $details = $this->resource;

        // Separate parent and child changes
        $parentChanges = [];
        $childChanges = [];

        foreach ($details as $detail) {
            if ($detail->isParentModel()) {
                // This is a change to the parent model
                $parentChanges[] = $this->formatDetailChange($detail);
            } else {
                // This is a change to a child model
                $childChanges[] = $this->formatChildChange($detail);
            }
        }

        // Group child changes by model and model_id
        $groupedChildren = $this->groupChildChanges($childChanges);

        return [
            'batch_no' => $details->first()?->batch_no,
            'timestamp' => $details->first()?->timestamp->format('Y-m-d H:i:s'),
            'timestamp_human' => $details->first()?->timestamp->diffForHumans(),
            'changed_by' => [
                'id' => $details->first()?->changedBy?->id,
                'name' => $details->first()?->changedBy?->name ?? 'System',
                'email' => $details->first()?->changedBy?->email,
            ],

            // Parent model changes
            'parent_changes' => !empty($parentChanges) ? $parentChanges : null,

            // Child model changes (grouped)
            'child_changes' => !empty($groupedChildren) ? $groupedChildren : null,
        ];
    }

    /**
     * Format a detail change
     */
    protected function formatDetailChange($detail): array
    {
        return [
            'event' => $detail->event,
            'changes' => $this->formatChanges($detail),
        ];
    }

    /**
     * Format a child model change with related data
     */
    protected function formatChildChange($detail): array
    {
        return [
            'model' => $detail->model,
            'model_id' => $detail->model_id,
            'model_name' => class_basename($detail->model),
            'event' => $detail->event,
            'changes' => $this->formatChanges($detail),
            'related_data' => $detail->getRelatedData(), // Load configured relations
        ];
    }

    /**
     * Group child changes by model and model_id
     * This groups all changes to the same record together
     */
    protected function groupChildChanges(array $childChanges): array
    {
        $grouped = [];

        foreach ($childChanges as $change) {
            // Create a unique key for this model instance
            $key = $change['model'] . '_' . $change['model_id'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'model' => $change['model'],
                    'model_id' => $change['model_id'],
                    'model_name' => $change['model_name'],
                    'related_data' => $change['related_data'],
                    'changes' => [],
                ];
            }

            // Merge changes for this model instance
            $grouped[$key]['changes'] = array_merge(
                $grouped[$key]['changes'],
                $change['changes']
            );
        }

        // Return as array (not associative)
        return array_values($grouped);
    }

    /**
     * Format changes from detail
     */
    protected function formatChanges($detail): array
    {
        $formatted = [];
        $old = $detail->changes['old'] ?? [];
        $new = $detail->changes['new'] ?? [];

        // For created events, only show new values
        if ($detail->event === 'created') {
            foreach ($new as $field => $newValue) {
                $formatted[] = [
                    'field' => $field,
                    'label' => $this->getFieldLabel($field),
                    'old' => null,
                    'new' => $this->formatValue($field, $newValue),
                    'type' => 'added',
                ];
            }
        }
        // For deleted events, only show old values
        elseif ($detail->event === 'deleted') {
            foreach ($old as $field => $oldValue) {
                $formatted[] = [
                    'field' => $field,
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
                    'field' => $field,
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
            return number_format((float)$value, 2);
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
