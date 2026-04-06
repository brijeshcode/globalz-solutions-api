<?php

namespace Tests\Feature\Mirror\Concerns;

use App\Helpers\FeatureHelper;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Tenant;
use App\Models\User;

trait HasMirrorSetup
{
    protected User $superAdmin;

    public function setUpMirror(bool $featureEnabled = true): void
    {
        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->superAdmin, 'sanctum');

        $tenantId = Tenant::current()?->id;

        if ($featureEnabled) {
            $this->enableDatabaseMirrorFeature();
        } elseif ($tenantId) {
            // Disable the feature in DB and clear cache
            $feature = Feature::where('key', 'database_mirror')->first();
            if ($feature) {
                TenantFeature::where('tenant_id', $tenantId)
                    ->where('feature_id', $feature->id)
                    ->update(['is_enabled' => false]);
            }
            TenantFeature::clearCache($tenantId);
        }

        FeatureHelper::flush();
    }

    protected function enableDatabaseMirrorFeature(): void
    {
        $feature = Feature::firstOrCreate(
            ['key' => 'database_mirror'],
            ['name' => 'Database Mirror', 'description' => 'Mirror tenant DB to remote MySQL', 'is_active' => true]
        );

        TenantFeature::updateOrCreate(
            ['tenant_id' => Tenant::current()->id, 'feature_id' => $feature->id],
            ['is_enabled' => true]
        );

        FeatureHelper::flush();
    }
}
