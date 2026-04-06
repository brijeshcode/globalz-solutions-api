<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Requests\Api\Settings\EmployeeSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class EmployeeSettingsController extends Controller
{
    private const GROUP = 'employee_settings';

    private const DEFAULTS = [
        'disable_payment_date_change' => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'disable_payment_order_date_change' => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
    ];

    /**
     * Get all employee settings. Open to all authenticated users.
     */
    public function get(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);

        $defaults = array_map(fn($config) => $config['value'], self::DEFAULTS);
        $settings = array_merge($defaults, $settings);

        return ApiResponse::show('Employee settings', $settings);
    }

    /**
     * Update employee settings. Restricted to canSuperAdmin.
     */
    public function update(EmployeeSettingsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_STRING;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Employee settings updated successfully', $updated);
    }

    /**
     * Reset employee settings to defaults. Restricted to canSuperAdmin.
     */
    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Employee settings reset to defaults', $settings);
    }
}
