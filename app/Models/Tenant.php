<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;

class Tenant extends BaseTenant
{

    protected $fillable = [
        'name',
        'domain',
        'tenant_key',
        'database',
        'database_username',
        'database_password',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'database_password',
    ];

    /**
     * Get encrypted database password
     */
    public function getDatabasePasswordAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Set encrypted database password
     */
    public function setDatabasePasswordAttribute($value): void
    {
        $this->attributes['database_password'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get database configuration for this tenant
     */
    public function getDatabaseConfig(): array
    {
        $defaultConnection = config('database.default');
        $baseConfig = config("database.connections.{$defaultConnection}");

        return array_merge($baseConfig, [
            'database' => $this->database,
            'username' => $this->database_username ?: $baseConfig['username'],
            'password' => $this->database_password ?: $baseConfig['password'],
        ]);
    }

    /**
     * Execute the job when tenant switches
     * This is called by Spatie automatically
     */
    public function makeCurrent(): static
    {
        parent::makeCurrent();

        // Set custom database connection with tenant-specific credentials
        $config = $this->getDatabaseConfig();
        config(['database.connections.tenant' => $config]);

        // Purge the connection to force reconnect with new config
        DB::purge('tenant');

        // Set tenant connection as default for migrations and queries
        config(['database.default' => 'tenant']);
        DB::purge('mysql'); // Also purge default connection
        DB::reconnect('tenant'); // Reconnect tenant connection

        return $this;
    }

    /**
     * Check if tenant has a feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->settings['features'] ?? []);
    }

    /**
     * Get tenant setting
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Relationships
     */

    /**
     * Get all features for this tenant
     */
    public function features()
    {
        return $this->belongsToMany(Feature::class, 'tenant_features')
            ->withPivot('is_enabled', 'settings')
            ->withTimestamps();
    }

    /**
     * Get tenant features pivot records
     */
    public function tenantFeatures()
    {
        return $this->hasMany(TenantFeature::class);
    }

    /**
     * Check if tenant has a feature enabled (using new features table)
     * @param string $featureKey - The feature key (e.g., 'advanced_reporting')
     */
    public function hasFeatureEnabled(string $featureKey): bool
    {
        return $this->features()
            ->where('key', $featureKey)
            ->where('features.is_active', true)
            ->wherePivot('is_enabled', true)
            ->exists();
    }

    /**
     * Get enabled features list (returns array of feature keys)
     */
    public function getEnabledFeatures(): array
    {
        return $this->features()
            ->where('features.is_active', true)
            ->wherePivot('is_enabled', true)
            ->pluck('key')
            ->toArray();
    }
}