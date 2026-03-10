<?php

namespace App\Models\Landlord;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TenantFeature extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'feature_id',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings'   => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    // ─── Static helpers ───────────────────────────────────────────────────────

    /**
     * Return all feature flags for the current tenant as key => bool map.
     * Result is cached per tenant for 1 hour.
     */
    public static function getForCurrentTenant(): array
    {
        $tenantId = Tenant::current()?->id;

        if (!$tenantId) {
            return [];
        }

        return Cache::remember("tenant_features:{$tenantId}", 3600, function () use ($tenantId) {
            return self::where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->with('feature:id,key')
                ->get()
                ->filter(fn($tf) => $tf->feature)
                ->mapWithKeys(fn($tf) => [$tf->feature->key => true])
                ->toArray();
        });
    }

    /**
     * Check if a single feature is enabled for the current tenant.
     */
    public static function isEnabled(string $featureKey): bool
    {
        return self::getForCurrentTenant()[$featureKey] ?? false;
    }

    /**
     * Clear the cached feature flags for a given tenant.
     * Call this after updating tenant_features in the landlord DB.
     */
    public static function clearCache(int $tenantId): void
    {
        Cache::forget("tenant_features:{$tenantId}");
    }
}
