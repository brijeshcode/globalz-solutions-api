<?php

namespace App\Models\ActivityLog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLogDetail extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'activity_log_details';

    /**
     * Indicates if the model should be timestamped.
     * We use custom 'timestamp' field instead of created_at/updated_at
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'activity_log_id',
        'batch_no',
        'model',
        'model_id',
        'event',
        'changes',
        'changed_by',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'changes' => 'array',
        'timestamp' => 'datetime',
        'batch_no' => 'integer',
    ];

    /**
     * Get the parent activity log
     */
    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class, 'activity_log_id');
    }

    /**
     * Get the user who made this change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the subject model instance (the record that was changed)
     */
    public function subject()
    {
        if (!$this->model || !class_exists($this->model)) {
            return null;
        }

        return $this->model::find($this->model_id);
    }

    /**
     * Get old values from changes JSON
     */
    public function getOldValues(): ?array
    {
        return $this->changes['old'] ?? null;
    }

    /**
     * Get new values from changes JSON
     */
    public function getNewValues(): ?array
    {
        return $this->changes['new'] ?? null;
    }

    /**
     * Check if this is a creation event
     */
    public function isCreated(): bool
    {
        return $this->event === 'created';
    }

    /**
     * Check if this is an update event
     */
    public function isUpdated(): bool
    {
        return $this->event === 'updated';
    }

    /**
     * Check if this is a deletion event
     */
    public function isDeleted(): bool
    {
        return $this->event === 'deleted';
    }

    /**
     * Scope to filter by batch number
     */
    public function scopeInBatch($query, int $batchNo)
    {
        return $query->where('batch_no', $batchNo);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope to order by most recent
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('timestamp', 'desc');
    }

    /**
     * Get related data based on configuration
     * Returns an array of related model data for display purposes
     */
    public function getRelatedData(): ?array
    {
        $config = config("activitylog.model_relations.{$this->model}");

        // No relations configured for this model
        if (!$config || empty($config)) {
            return null;
        }

        $subject = $this->subject();

        // Subject doesn't exist (deleted or invalid)
        if (!$subject) {
            return null;
        }

        $relatedData = [];

        foreach ($config as $relationName => $fields) {
            try {
                // Load the relation
                $relation = $subject->{$relationName};

                if ($relation) {
                    // Get the relation's model class
                    $relationModel = get_class($relation);

                    // Get field mappings for this relation's model
                    $fieldMappings = config("activitylog.model_field_mappings.{$relationModel}", []);

                    // Build the data with field aliasing
                    $data = [];
                    foreach ($fields as $requestedField) {
                        // Check if this field has a mapping (alias)
                        $actualField = $fieldMappings[$requestedField] ?? $requestedField;

                        // Get the value from the actual field
                        if (isset($relation->{$actualField})) {
                            // Store it with the requested field name
                            $data[$requestedField] = $relation->{$actualField};
                        }
                    }

                    $relatedData[$relationName] = $data;
                }
            } catch (\Exception $e) {
                // Relation doesn't exist or error loading - skip it
                continue;
            }
        }

        return !empty($relatedData) ? $relatedData : null;
    }

    /**
     * Check if this detail belongs to the parent model (vs a child model)
     */
    public function isParentModel(): bool
    {
        $activityLog = $this->activityLog;

        return $activityLog &&
               $this->model === $activityLog->model &&
               $this->model_id === $activityLog->model_id;
    }
}
