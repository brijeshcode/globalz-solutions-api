<?php

namespace App\Traits;

use App\Helpers\FeatureHelper;
use App\Models\SyncToOld;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Gives a transaction model a "copied to legacy system" flag backed by the
 * central sync_to_old table (see the "syncin" feature). No column is added to
 * the model's own table — the flag is keyed by table name + id.
 *
 * The flag is surfaced as the `is_synced_to_old` accessor and is eager-loaded
 * automatically, but only when the feature is effectively enabled, so tenants
 * without it pay nothing beyond an in-memory feature lookup.
 */
trait SyncableToOld
{
    public static function bootSyncableToOld(): void
    {
        static::addGlobalScope('syncToOldEagerLoad', function (Builder $builder) {
            if (FeatureHelper::isSyncin()) {
                $builder->with('syncToOld');
            }
        });
    }

    /**
     * @return HasOne<SyncToOld, $this>
     */
    public function syncToOld(): HasOne
    {
        return $this->hasOne(SyncToOld::class, 'model_id')
            ->where('model', $this->getTable());
    }

    public function getIsSyncedToOldAttribute(): bool
    {
        return (bool) ($this->syncToOld?->is_synced ?? false);
    }
}
