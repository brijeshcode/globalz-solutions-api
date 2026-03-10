<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    /**
     * Settings that can only be written once.
     * If the key already has a non-empty value in the tenant DB, it will be rejected.
     *
     * Format: 'group.key'
     */
    private const ONE_TIME_KEYS = [
        'currency.local_currency',
    ];

    /**
     * All settings this endpoint is allowed to manage, with their validation rules.
     * Add new controllable settings here as needed.
     */
    private const ALLOWED_SETTINGS = [
        'local_currency' => ['group' => 'currency', 'rule' => 'required|string|size:3'],
    ];

    /**
     * Update one or more landlord-controlled settings for a tenant.
     *
     * PATCH /tenants/{tenant}/settings
     *
     * Body: any subset of keys defined in ALLOWED_SETTINGS.
     * Keys listed in ONE_TIME_KEYS will be rejected if already set.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        // Build validation rules dynamically from ALLOWED_SETTINGS
        $rules = [];
        foreach (self::ALLOWED_SETTINGS as $key => $config) {
            $rules[$key] = 'sometimes|' . $config['rule'];
        }

        $validated = $request->validate($rules);

        if (empty($validated)) {
            return ApiResponse::failValidation([
                'fields' => 'Provide at least one valid setting key: ' . implode(', ', array_keys(self::ALLOWED_SETTINGS)),
            ]);
        }

        // Check for one-time keys that are already set in the tenant DB
        $locked = $tenant->execute(function () use ($validated) {
            $lockedKeys = [];

            foreach ($validated as $key => $value) {
                $config    = self::ALLOWED_SETTINGS[$key];
                $lookupKey = $config['group'] . '.' . $key;

                if (in_array($lookupKey, self::ONE_TIME_KEYS, true)) {
                    $existing = Setting::where('group_name', $config['group'])
                        ->where('key_name', $key)
                        ->whereNotNull('value')
                        ->where('value', '!=', '')
                        ->exists();

                    if ($existing) {
                        $lockedKeys[] = $key;
                    }
                }
            }

            return $lockedKeys;
        });

        if (!empty($locked)) {
            return ApiResponse::failValidation([
                'locked' => 'These settings are already set and cannot be changed: ' . implode(', ', $locked),
            ]);
        }

        // Apply the updates inside tenant context
        $tenant->execute(function () use ($validated) {
            foreach ($validated as $key => $value) {
                $group = self::ALLOWED_SETTINGS[$key]['group'];
                Setting::set($group, $key, $value);
            }

            AttachCacheVersion::invalidate('tenant_details');
        });

        // Return the current state of all managed settings
        $current = $tenant->execute(function () {
            $result = [];
            foreach (self::ALLOWED_SETTINGS as $key => $config) {
                $result[$key] = Setting::get($config['group'], $key);
            }
            return $result;
        });

        return ApiResponse::update('Tenant settings updated successfully', [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'settings'    => $current,
        ]);
    }

    /**
     * Get current landlord-controlled settings for a tenant.
     *
     * GET /tenants/{tenant}/settings
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $settings = $tenant->execute(function () {
            $result = [];
            foreach (self::ALLOWED_SETTINGS as $key => $config) {
                $lookupKey = $config['group'] . '.' . $key;
                $result[$key] = [
                    'value'    => Setting::get($config['group'], $key),
                    'locked'   => in_array($lookupKey, self::ONE_TIME_KEYS, true)
                                  && !empty(Setting::get($config['group'], $key)),
                ];
            }
            return $result;
        });

        return ApiResponse::show('Tenant settings', [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'settings'    => $settings,
        ]);
    }
}
