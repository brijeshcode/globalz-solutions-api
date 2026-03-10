<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Authorable
{
    /**
     * Resolve the auth user ID that is safe to write in the current tenant DB.
     * Returns null when the authenticated user (e.g. a cross-tenant developer)
     * does not exist in this tenant's users table, preventing FK violations.
     */
    private static function resolveAuthorId(): ?int
    {
        if (!Auth::check()) {
            return null;
        }

        $userId = Auth::id();

        // Cache per-request per-user so we only hit the DB once
        static $cache = [];
        if (!array_key_exists($userId, $cache)) {
            $cache[$userId] = DB::table('users')->where('id', $userId)->exists();
        }

        return $cache[$userId] ? $userId : null;
    }

    protected static function bootAuthorable()
    {
        // Set created_by when creating
        static::creating(function ($model) {
            $authorId = static::resolveAuthorId();
            $model->created_by = $authorId;
            $model->updated_by = $authorId;
        });

        // Set updated_by when updating
        static::updating(function ($model) {
            $model->updated_by = static::resolveAuthorId();
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
