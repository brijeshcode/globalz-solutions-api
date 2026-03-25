<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Requests\Api\Customers\CustomerSaleSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SaleSettingsController extends Controller
{
    private const GROUP = 'sale_settings';

    private const DEFAULTS = [
        'block_new_sale'         => ['value' => false,       'type' => Setting::TYPE_BOOLEAN],
        'block_new_sale_order'   => ['value' => false,       'type' => Setting::TYPE_BOOLEAN],
        'block_return_sale_received'   => ['value' => false,       'type' => Setting::TYPE_BOOLEAN],
    ];
    
    /**
     * Get all get sale settings.
     */
    public function get(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);

        // Merge with defaults so every key is always present in the response
        $defaults = array_map(fn($config) => $config['value'], self::DEFAULTS);
        $settings = array_merge($defaults, $settings);

        return ApiResponse::show('Sale settings', $settings);
    }

    public function update(CustomerSaleSettingsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_STRING;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Sales settings updated successfully', $updated);
    }

    /**
     * Reset invoice settings to defaults.
     */
    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Sales settings reset to defaults', $settings);
    }

}
