<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Requests\Api\Settings\ModuleLockSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ModuleLockSettingsController extends Controller
{
    private const GROUP = 'module_locks';

    /**
     * Days after which a record is locked for edit/delete. 0 = disabled.
     * Orders (pending documents) lock by age alone; lifecycle documents are
     * exempt until final (delivered / received / paid) — see ModuleLockable.
     */
    private const DEFAULTS = [
        'sale'                    => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'sale_order'              => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'purchase'                => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'customer_payment'        => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'customer_payment_order'  => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'customer_return'         => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'customer_return_order'   => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'customer_credit_note'    => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'supplier_credit_note'    => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'supplier_payment'        => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'expense'                 => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
        'expense_payment'         => ['value' => 0, 'type' => Setting::TYPE_NUMBER],
    ];

    /**
     * Get all module lock settings.
     */
    public function get(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);

        // Merge with defaults so every key is always present in the response
        $defaults = array_map(fn ($config) => $config['value'], self::DEFAULTS);
        $settings = array_merge($defaults, $settings);

        return ApiResponse::show('Module lock settings', $settings);
    }

    public function update(ModuleLockSettingsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_NUMBER;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Module lock settings updated successfully', $updated);
    }

    /**
     * Reset module lock settings to defaults (all disabled).
     */
    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = Setting::getGroup(self::GROUP);

        AttachCacheVersion::invalidate(self::GROUP);

        return ApiResponse::update('Module lock settings reset to defaults', $settings);
    }
}
