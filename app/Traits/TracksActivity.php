<?php

namespace App\Traits;

use App\Jobs\LogActivityJob;
use App\Services\ActivityLog\ActivityLogHandler;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Trait for models that need automatic activity logging
 *
 * This trait automatically logs create, update, and delete events to the custom activity log system.
 * Works with both parent models (Sale, Purchase, Expense) and child models (SaleItems, etc.)
 *
 * Usage:
 * 1. Add this trait to your model
 * 2. Define getActivityLogParent() method if this is a child model (e.g., SaleItems)
 * 3. Optionally define getActivityDisplayName() to customize the display name
 * 4. Optionally define getActivityLogAttributes() to specify which fields to log
 */
trait TracksActivity
{
    /**
     * Global flag to enable/disable activity tracking
     */
    protected static $trackingEnabled = true;

    /**
     * Global attributes to ignore across all models
     * These will never be logged
     */
    protected static $globalIgnoreAttributes = [
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Boot the trait and register model event listeners
     */
    protected static function bootTracksActivity()
    {
        // Listen for updated event
        static::updated(function ($model) {
            $model->logActivity('updated');
        });

        // Listen for deleted event
        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    /**
     * Disable activity tracking globally
     */
    public static function disableTracking(): void
    {
        static::$trackingEnabled = false;
    }

    /**
     * Enable activity tracking globally
     */
    public static function enableTracking(): void
    {
        static::$trackingEnabled = true;
    }

    /**
     * Check if activity tracking is enabled
     */
    public static function isTrackingEnabled(): bool
    {
        return static::$trackingEnabled;
    }

    /**
     * Execute a callback without activity tracking
     */
    public static function withoutActivityTracking(callable $callback)
    {
        static::disableTracking();

        try {
            return $callback();
        } finally {
            static::enableTracking();
        }
    }

    /**
     * Log the activity by dispatching to queue
     */
    protected function logActivity(string $event)
    {
        // Check if tracking is enabled
        if (!static::isTrackingEnabled()) {
            return;
        }

        // Check if this model should skip logging
        if ($this->shouldSkipActivityLog()) {
            return;
        }

        // Get the user who made the change
        $userId = auth()->id();

        // Get changes for this event
        $changes = $this->getActivityChanges($event);

        // Skip logging if no changes for updated events
        if ($event === 'updated' && $this->hasNoChanges($changes)) {
            return;
        }

        // Prepare activity data
        $activityData = [
            'event' => $event,
            'model' => get_class($this),
            'model_id' => $this->getKey(),
            'changes' => $changes,
            'user_id' => $userId,
            'timestamp' => now(),
        ];

        // Determine if this is a parent or child model
        $parent = $this->getActivityLogParent();

        if ($parent) {
            // This is a child model - add parent info
            $activityData['parent_model'] = get_class($parent);
            $activityData['parent_id'] = $parent->getKey();
            $activityData['parent_display'] = $this->getParentDisplayName($parent);
        } else {
            // This is a parent model
            $activityData['parent_model'] = get_class($this);
            $activityData['parent_id'] = $this->getKey();
            $activityData['parent_display'] = $this->getActivityDisplayName();
        }

        // Check if async processing is enabled
        if (config('activitylog.async', false)) {
            // Async: Dispatch to queue for background processing
            LogActivityJob::dispatch($activityData);
        } else {
            // Sync: Process immediately
            try {
                app(ActivityLogHandler::class)->handle($activityData);
            } catch (\Exception $e) {
                // Log error but don't fail the main operation
                Log::error('Activity logging failed (sync)', [
                    'error' => $e->getMessage(),
                    'model' => get_class($this),
                    'id' => $this->getKey(),
                ]);
            }
        }
    }

    /**
     * Get the changes to be logged
     */
    protected function getActivityChanges(string $event): array
    {
        if ($event === 'updated') {
            // For update, log old and new values (only dirty attributes)
            $dirty = $this->getDirty();
            $original = $this->getOriginal();

            // Remove ignored attributes (global + model-specific)
            $ignoreAttributes = $this->getAllIgnoreAttributes();
            $dirty = array_diff_key($dirty, array_flip($ignoreAttributes));
            $original = array_diff_key($original, array_flip($ignoreAttributes));

            // Filter to only log specified attributes if defined
            $logAttributes = $this->getActivityLogAttributes();
            if (!empty($logAttributes)) {
                $dirty = array_intersect_key($dirty, array_flip($logAttributes));
                $original = array_intersect_key($original, array_flip($logAttributes));
            }

            return [
                'old' => array_intersect_key($original, $dirty),
                'new' => $dirty,
            ];
        } elseif ($event === 'deleted') {
            // For delete, log the final state
            return [
                'old' => $this->getActivityAttributes(),
            ];
        }

        return [];
    }

    /**
     * Get attributes to log (either all or specific ones)
     */
    protected function getActivityAttributes(): array
    {
        $logAttributes = $this->getActivityLogAttributes();

        if (!empty($logAttributes)) {
            // Only return specified attributes
            return array_intersect_key($this->getAttributes(), array_flip($logAttributes));
        }

        // Get all ignored attributes (global + model-specific)
        $ignoreAttributes = $this->getAllIgnoreAttributes();

        // Return all attributes except ignored ones
        return collect($this->getAttributes())
            ->except($ignoreAttributes)
            ->toArray();
    }

    /**
     * Get all attributes to ignore (global + model-specific)
     */
    protected function getAllIgnoreAttributes(): array
    {
        // Get model-specific ignore list from property
        $modelIgnores = property_exists($this, 'activityIgnore') ? $this->activityIgnore : [];

        // Merge global ignore list with model-specific ignore list
        return array_merge(
            static::$globalIgnoreAttributes,
            $modelIgnores
        );
    }

    /**
     * Get the parent model for activity logging
     * Override this in child models (e.g., SaleItems should return the parent Sale)
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getActivityLogParent()
    {
        // Default: this is a parent model (no parent)
        return null;
    }

    /**
     * Get the display name for this model
     * Override this to customize the display name
     */
    public function getActivityDisplayName(): string
    {
        // Try common identifier fields
        if (isset($this->prefix) && isset($this->code)) {
            return $this->prefix . $this->code;
        }

        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->description)) {
            return $this->description;
        }

        if (isset($this->title)) {
            return $this->title;
        }

        // Fallback: Model name + ID
        return class_basename($this) . ' #' . $this->getKey();
    }

    /**
     * Get parent display name
     */
    protected function getParentDisplayName($parent): string
    {
        if (method_exists($parent, 'getActivityDisplayName')) {
            return $parent->getActivityDisplayName();
        }

        return class_basename($parent) . ' #' . $parent->getKey();
    }

    /**
     * Get the attributes that should be logged
     * Override this in your model to specify which attributes to track
     * Return empty array to log all attributes (default)
     *
     * @return array
     */
    protected function getActivityLogAttributes(): array
    {
        // Default: log all attributes
        // Override in your model like:
        // return ['customer_id', 'total_usd', 'status'];
        return [];
    }

    /**
     * Check if activity logging should be skipped for this event
     * Override this to add custom logic for when to skip logging
     */
    protected function shouldSkipActivityLog(): bool
    {
        // Default: never skip
        // Override in your model to add conditions, like:
        // return !$this->is_approved; // Only log after approval
        return false;
    }

    /**
     * Check if changes array is empty
     * Returns true if there are no actual changes to log
     */
    protected function hasNoChanges(array $changes): bool
    {
        // Check if both old and new are empty
        $old = $changes['old'] ?? [];
        $new = $changes['new'] ?? [];

        return empty($old) && empty($new);
    }
}
