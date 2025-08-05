<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Authorable
{
    protected static function bootHasAuditFields()
    {
        // Set created_by when creating
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = auth::id();
                $model->updated_by = auth::id();
            }
        });

        // Set updated_by when updating
        static::updating(function ($model) {
            if (auth::check()) {
                $model->updated_by = auth::id();
            }
        });
    }

    /**
     * Get the user who created this record
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter by creator
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter by updater
     */
    public function scopeUpdatedBy($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }
}
