<?php

namespace App\Services\ActivityLog;

use App\Models\ActivityLog\ActivityLog;
use App\Models\ActivityLog\ActivityLogDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handler for processing activity log records
 *
 * This service contains the core logic for creating and updating activity logs.
 * It can be used both synchronously (direct calls) and asynchronously (via jobs).
 */
class ActivityLogHandler
{
    /**
     * Process activity log data
     *
     * @param array $activityData
     * @return void
     * @throws \Exception
     */
    public function handle(array $activityData): void
    {
        try {
            DB::beginTransaction();

            // Extract data
            $event = $activityData['event'];
            $model = $activityData['model'];
            $modelId = $activityData['model_id'];
            $changes = $activityData['changes'];
            $userId = $activityData['user_id'];
            $timestamp = $activityData['timestamp'];

            // Parent info (could be same as model for parent entities)
            $parentModel = $activityData['parent_model'];
            $parentId = $activityData['parent_id'];
            $parentDisplay = $activityData['parent_display'];

            // Find or create the parent activity log
            $activityLog = ActivityLog::where('model', $parentModel)
                ->where('model_id', $parentId)
                ->first();

            if (!$activityLog) {
                // First time - create new activity log
                $activityLog = ActivityLog::create([
                    'model' => $parentModel,
                    'model_id' => $parentId,
                    'model_display' => $parentDisplay,
                    'last_event_type' => $event,
                    'last_batch_no' => 1,
                    'last_changed_by' => $userId,
                    'timestamp' => $timestamp,
                    'seen_all' => false,
                ]);

                $batchNo = 1;
            } else {
                // Subsequent change - check if we need to increment batch
                $batchNo = $activityLog->last_batch_no;

                // Check if this is a new batch
                $shouldIncrementBatch = $this->shouldIncrementBatch(
                    $activityLog,
                    $timestamp,
                    $model,
                    $parentModel
                );

                if ($shouldIncrementBatch) {
                    $batchNo = $activityLog->last_batch_no + 1;
                }

                // Update the activity log
                $activityLog->update([
                    'last_event_type' => $event,
                    'last_batch_no' => $batchNo,
                    'last_changed_by' => $userId,
                    'timestamp' => $timestamp,
                    'seen_all' => false, // Reset seen flag
                ]);
            }

            // Create activity log detail
            ActivityLogDetail::create([
                'activity_log_id' => $activityLog->id,
                'batch_no' => $batchNo,
                'model' => $model,
                'model_id' => $modelId,
                'event' => $event,
                'changes' => $changes,
                'changed_by' => $userId,
                'timestamp' => $timestamp,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Activity logging failed', [
                'error' => $e->getMessage(),
                'data' => $activityData,
            ]);

            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Determine if we should increment the batch number
     *
     * Batch should be incremented if:
     * 1. Time gap > configured batch_window since last change
     */
    protected function shouldIncrementBatch(
        ActivityLog $activityLog,
        $timestamp,
        string $currentModel,
        string $parentModel
    ): bool {
        $batchWindow = config('activitylog.batch_window', 2);

        // If this is the parent model itself, check time gap
        if ($currentModel === $parentModel) {
            $lastTimestamp = $activityLog->timestamp;
            $timeDiff = strtotime($timestamp) - strtotime($lastTimestamp);

            // Increment if time gap > batch window
            return $timeDiff > $batchWindow;
        }

        // For child models, check if there are already details in this batch
        $lastDetail = ActivityLogDetail::where('activity_log_id', $activityLog->id)
            ->where('batch_no', $activityLog->last_batch_no)
            ->latest('timestamp')
            ->first();

        if (!$lastDetail) {
            // No details in current batch yet, use current batch
            return false;
        }

        $timeDiff = strtotime($timestamp) - strtotime($lastDetail->timestamp);

        // If last detail was more than batch window ago, start new batch
        return $timeDiff > $batchWindow;
    }
}
