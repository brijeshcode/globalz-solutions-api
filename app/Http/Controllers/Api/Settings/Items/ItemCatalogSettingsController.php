<?php

namespace App\Http\Controllers\Api\Settings\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\Items\ItemCatalogSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ItemCatalogSettingsController extends Controller
{
    private const GROUP = 'item_catalog';

    private const DEFAULTS = [
        'inv_show_qrcode'   => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'inv_catalog_link'  => ['value' => null,  'type' => Setting::TYPE_STRING],
        'inv_catalog_label' => ['value' => null,  'type' => Setting::TYPE_STRING],
        'inx_show_qrcode'   => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'inx_catalog_link'  => ['value' => null,  'type' => Setting::TYPE_STRING],
        'inx_catalog_label' => ['value' => null,  'type' => Setting::TYPE_STRING],
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);

        return ApiResponse::show('Item catalog settings retrieved successfully', $settings);
    }

    public function update(ItemCatalogSettingsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_STRING;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = Setting::getGroup(self::GROUP);

        return ApiResponse::update('Item catalog settings updated successfully', $updated);
    }

    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = Setting::getGroup(self::GROUP);

        return ApiResponse::update('Item catalog settings reset to defaults', $settings);
    }
}
