<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Trait for models that need activity logging after approval
 *
 * This trait internally uses LogsActivity, so you don't need to add it separately
 *
 * This trait provides activity logging that only tracks changes AFTER a record is approved.
 * It prevents logging:
 * - The initial approval action itself
 * - Any changes before approval
 *
 * Requirements:
 * - Model must have 'approved_at' column
 * - Model must use Spatie\Activitylog\Traits\LogsActivity trait
 *
 * Usage:
 * 1. Add LogsActivity trait to your model
 * 2. Add this trait to your model
 * 3. Define getActivityLogAttributes() method to specify which fields to log
 * 4. Optionally define getActivityLogName() method to customize log name
 */
trait HasApprovalBasedActivityLog
{
    use LogsActivity;

    /**
     * Get the attributes to be logged
     * Override this method in your model to specify which fields to track
     *
     * @return array
     */
    abstract protected function getActivityLogAttributes(): array;

    /**
     * Get the log name for this model
     * Override this method in your model to customize the log name
     *
     * @return string
     */
    protected function getActivityLogName(): string
    {
        // Default: use snake_case of model name
        return strtolower(class_basename(static::class));
    }

    /**
     * Get the description prefix for events
     * Override this method in your model to customize event descriptions
     *
     * @return string
     */
    protected function getActivityLogDescription(): string
    {
        // Default: use model name
        return class_basename(static::class);
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getActivityLogAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getActivityLogName())
            ->setDescriptionForEvent(fn(string $eventName) =>
                $this->getActivityLogDescription() . " {$eventName}"
            );
    }

    /**
     * Specify which events to log (only updated in this case)
     */
    protected static function eventsToBeRecorded(): Collection
    {
        return collect(['updated']);
    }

    /**
     * Determine if the event should be logged
     * Only log updates after the record is approved
     */
    public function shouldLogEvent(string $eventName): bool
    {
        // Only proceed for update events
        if ($eventName !== 'updated') {
            return false;
        }

        // Get the original approved_at value
        $originalApprovedAt = $this->getOriginal('approved_at');

        // If approved_at is changing from null to a value (initial approval), don't log
        if (is_null($originalApprovedAt) && !is_null($this->approved_at)) {
            return false;
        }

        // If the record is not approved (approved_at is null), don't log
        if (is_null($this->approved_at)) {
            return false;
        }

        // Otherwise, log the update (record is already approved and this is a subsequent change)
        return true;
    }
}
