<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;

class Feature extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'key',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all tenants that have this feature
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_features')
            ->withPivot('is_enabled', 'settings')
            ->withTimestamps();
    }

    /**
     * Get tenant features pivot records
     *
     * @return HasMany<TenantFeature, $this>
     */
    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    /**
     * Bundles that include this feature.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<FeatureBundle, $this>
     */
    public function bundles()
    {
        return $this->belongsToMany(FeatureBundle::class, 'bundle_features')
            ->withTimestamps();
    }
}
