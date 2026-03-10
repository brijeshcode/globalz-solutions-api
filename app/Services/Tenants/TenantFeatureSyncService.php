<?php

namespace App\Services\Tenants;

use App\Http\Middleware\AttachCacheVersion;
use App\Models\Landlord\FeatureBundle;
use App\Models\Landlord\TenantFeature;
use App\Models\Tenant;

class TenantFeatureSyncService
{
    /**
     * Apply all active features from a bundle to a tenant.
     * One-time template action — no ongoing bundle→tenant link.
     */
    public function applyBundle(Tenant $tenant, FeatureBundle $bundle): void
    {
        $features = $bundle->features()
            ->where('is_active', true)
            ->get(['features.id', 'features.key']);

        foreach ($features as $feature) {
            TenantFeature::updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                ['is_enabled' => true]
            );
        }

        TenantFeature::clearCache($tenant->id);
        AttachCacheVersion::invalidate('features');
    }

    /**
     * Enable or disable a single feature for a tenant.
     */
    public function syncSingleFeature(Tenant $tenant, int $featureId, bool $isEnabled): void
    {
        TenantFeature::updateOrCreate(
            ['tenant_id' => $tenant->id, 'feature_id' => $featureId],
            ['is_enabled' => $isEnabled]
        );

        TenantFeature::clearCache($tenant->id);
        AttachCacheVersion::invalidate('features');
    }

    /**
     * Bulk-write all tenant_features for a tenant (used after bulk update).
     */
    public function pushToTenantSettings(Tenant $tenant): void
    {
        TenantFeature::clearCache($tenant->id);
        AttachCacheVersion::invalidate('features');
    }
}
