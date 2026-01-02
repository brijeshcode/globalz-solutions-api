<?php

namespace App\Models\ActivityLog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'activity_logs';

    /**
     * Indicates if the model should be timestamped.
     * We use custom 'timestamp' field instead of created_at/updated_at
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model',
        'model_id',
        'model_display',
        'last_event_type',
        'last_batch_no',
        'last_changed_by',
        'timestamp',
        'seen_all',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'seen_all' => 'boolean',
        'last_batch_no' => 'integer',
    ];

    /**
     * Get all detail records for this activity log
     */
    public function details(): HasMany
    {
        return $this->hasMany(ActivityLogDetail::class, 'activity_log_id')
            ->orderBy('batch_no', 'desc')
            ->orderBy('timestamp', 'desc');
    }

    /**
     * Get details grouped by batch number
     */
    public function detailsByBatch()
    {
        return $this->details()->get()->groupBy('batch_no');
    }

    /**
     * Get the user who made the last change
     */
    public function lastChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }

    /**
     * Get the subject model instance (the entity being tracked)
     */
    public function subject()
    {
        if (!$this->model || !class_exists($this->model)) {
            return null;
        }

        return $this->model::find($this->model_id);
    }

    /**
     * Mark all changes as seen
     */
    public function markAsSeen(): bool
    {
        return $this->update(['seen_all' => true]);
    }

    /**
     * Mark as unseen
     */
    public function markAsUnseen(): bool
    {
        return $this->update(['seen_all' => false]);
    }

    /**
     * Increment batch number and update metadata
     */
    public function incrementBatch(int $userId = null): int
    {
        $newBatchNo = $this->last_batch_no + 1;

        $this->update([
            'last_batch_no' => $newBatchNo,
            'last_changed_by' => $userId,
            'timestamp' => now(),
            'seen_all' => false, // Reset seen flag when new changes occur
        ]);

        return $newBatchNo;
    }

    /**
     * Scope to filter by model type
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('model', $modelClass);
    }

    /**
     * Scope to filter unseen logs
     */
    public function scopeUnseen($query)
    {
        return $query->where('seen_all', false);
    }

    /**
     * Scope to order by most recent
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('timestamp', 'desc');
    }
}
