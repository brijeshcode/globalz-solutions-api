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
    /**
     * The connection name for the model.
     * Tenants table lives in the landlord database
     */
    protected $connection = 'mysql';

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
        // Already current with the tenant connection active — nothing to switch.
        // Skipping matters: queued jobs call makeCurrent() on every process, and the
        // purge below would needlessly drop live connections (and, on the sync
        // driver, destroy any open transaction — e.g. the one wrapping each test).
        // Context is still (re)stamped so job payloads dispatched after this call
        // always carry the tenant id.
        if (static::current()?->getKey() === $this->getKey() && config('database.default') === 'tenant') {
            \Illuminate\Support\Facades\Context::add(config('multitenancy.current_tenant_context_key'), $this->getKey());

            return $this;
        }

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
     * Counterpart to the makeCurrent() override: restore the landlord connection
     * as default. Without this, long-running CLI/queue processes keep pointing at
     * the last tenant's database after the tenant is forgotten.
     */
    public static function forgetCurrent(): ?static
    {
        $tenant = parent::forgetCurrent();

        config(['database.default' => 'mysql']);
        DB::purge('tenant');

        return $tenant;
    }

    /**
     * Run a callback once per active tenant, making each tenant current in turn.
     * Failures are logged per tenant and do not stop the loop. If the callback
     * returns an array, it is included in the completion log entry.
     * Pass $tenantIds to limit the run to specific tenants (e.g. from a --tenant option).
     */
    public static function runForEachActive(string $taskName, callable $callback, array $tenantIds = []): void
    {
        $tenants = static::on('mysql')
            ->where('is_active', true)
            ->when(!empty($tenantIds), fn ($query) => $query->whereIn('id', $tenantIds))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $tenant->makeCurrent();
                $result = $callback($tenant);
                info("{$taskName} completed for tenant {$tenant->tenant_key}", is_array($result) ? $result : []);
                // Destruct any PendingDispatch NOW — queued jobs capture the current
                // tenant at push time, which must happen before forgetCurrent() below.
                unset($result);
            } catch (\Throwable $e) {
                info("{$taskName} failed for tenant {$tenant->tenant_key}", ['error' => $e->getMessage()]);
            } finally {
                static::forgetCurrent();
            }
        }
    }

    /**
     * Override route model binding to use landlord connection
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::on('mysql')->where($field ?? $this->getRouteKeyName(), $value)->first();
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