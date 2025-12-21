<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Trait for child models that log activities to their parent model
 *
 * This trait internally uses LogsActivity, so you don't need to add it separately
 *
 * This trait provides activity logging that logs all changes to the parent model
 * instead of the child model itself. Only logs when parent is approved.
 *
 * Requirements:
 * - Model must use Spatie\Activitylog\Traits\LogsActivity trait
 * - Model must have a relationship to parent model
 * - Parent model must have 'approved_at' column
 *
 * Usage:
 * 1. Add LogsActivity trait to your model
 * 2. Add this trait to your model
 * 3. Define getActivityLogAttributes() method
 * 4. Define getParentRelationshipName() method
 * 5. Define getItemIdentifier() method
 */
trait LogsToParentModel
{
    use LogsActivity;

    /**
     * Flag to control activity logging globally
     */
    protected static bool $loggingEnabled = true;

    /**
     * Get the attributes to be logged
     * Override this method in your model
     *
     * @return array
     */
    abstract protected function getActivityLogAttributes(): array;

    /**
     * Get the parent relationship name
     * Override this method in your model (e.g., 'sale', 'customerReturn')
     *
     * @return string
     */
    abstract protected function getParentRelationshipName(): string;

    /**
     * Get item identifier for log description (e.g., item code)
     * Override this method in your model
     *
     * @return string
     */
    abstract protected function getItemIdentifier(): string;

    /**
     * Get the log name (should match parent's log name)
     * Override this method if needed
     *
     * @return string
     */
    protected function getActivityLogName(): string
    {
        // Default: use parent's log name
        $parentRelationship = $this->getParentRelationshipName();
        return strtolower(str_replace('_', '_', $parentRelationship));
    }

    /**
     * Get description for item events
     * Override this method to customize
     *
     * @return string
     */
    protected function getItemTypeDescription(): string
    {
        // Default: use model name without 'Item' suffix
        return str_replace('Item', ' item', class_basename(static::class));
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
            ->setDescriptionForEvent(function (string $eventName) {
                $itemId = $this->getItemIdentifier();
                $itemType = $this->getItemTypeDescription();

                return match($eventName) {
                    'created' => "{$itemType} added: {$itemId}",
                    'updated' => "{$itemType} updated: {$itemId}",
                    'deleted' => "{$itemType} removed: {$itemId}",
                    default => "{$itemType} {$eventName}: {$itemId}",
                };
            });
    }

    /**
     * Specify which events to log
     */
    protected static function eventsToBeRecorded(): Collection
    {
        return collect(['created', 'updated', 'deleted']);
    }

    /**
     * Attach activity log to the parent model
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $parentRelationship = $this->getParentRelationshipName();

        // Load the parent relationship if not already loaded
        if (!$this->relationLoaded($parentRelationship)) {
            $this->load($parentRelationship);
        }

        // Attach the activity to the parent
        $parent = $this->{$parentRelationship};
        if ($parent) {
            $activity->subject()->associate($parent);
        }
    }

    /**
     * Only log when the parent is approved
     */
    public function shouldLogEvent(string $eventName): bool
    {
        // Check if logging is globally disabled for this model
        if (!static::$loggingEnabled) {
            return false;
        }

        $parentRelationship = $this->getParentRelationshipName();

        // Load the parent relationship if not already loaded
        if (!$this->relationLoaded($parentRelationship)) {
            $this->load($parentRelationship);
        }

        $parent = $this->{$parentRelationship};

        // Don't log if parent doesn't exist or is not approved
        if (!$parent || is_null($parent->approved_at)) {
            return false;
        }

        // Prevent duplicate logging if parent's logging is disabled
        if (isset($parent->enableLoggingModelsEvents) && !$parent->enableLoggingModelsEvents) {
            return false;
        }

        return true;
    }

    /**
     * Disable activity logging
     */
    public static function disableLogging(): void
    {
        static::$loggingEnabled = false;
    }

    /**
     * Enable activity logging
     */
    public static function enableLogging(): void
    {
        static::$loggingEnabled = true;
    }
}
