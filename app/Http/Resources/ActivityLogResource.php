<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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

            // Entity info
            'model' => $this->model,
            'model_id' => $this->model_id,
            'model_display' => $this->model_display,
            'model_name' => class_basename($this->model),

            // Last change info
            'last_event_type' => $this->last_event_type,
            'last_batch_no' => $this->last_batch_no,

            // Timestamp
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'timestamp_human' => $this->timestamp->diffForHumans(),
            'date' => $this->timestamp->format('M d, Y'),
            'time' => $this->timestamp->format('h:i A'),

            // User who made last change
            'last_changed_by' => [
                'id' => $this->lastChangedBy?->id,
                'name' => $this->lastChangedBy?->name ?? 'System',
                'email' => $this->lastChangedBy?->email,
            ],

            // Status
            'seen_all' => $this->seen_all,

            // Include details grouped by batch if loaded
            'details_by_batch' => $this->when(
                $this->relationLoaded('details'),
                function () {
                    $detailsByBatch = $this->details->groupBy('batch_no');
                    $formatted = [];
                    foreach ($detailsByBatch as $batchNo => $details) {
                        $formatted[] = new ActivityLogBatchResource($details);
                    }
                    return $formatted;
                }
            ),

            // Batch count
            'total_batches' => $this->last_batch_no,
        ];
    }
}
