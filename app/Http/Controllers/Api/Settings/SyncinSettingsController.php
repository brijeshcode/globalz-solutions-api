<?php

namespace App\Http\Controllers\Api\Settings;

use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Tenant-level on/off switch for the syncin feature. This is the second gate
 * on top of the landlord feature flag — both must be on for the flag to show
 * (see FeatureHelper::isSyncin).
 */
class SyncinSettingsController extends Controller
{
    private const KEY = 'syncin_old_local_system';

    public function get(): JsonResponse
    {
        return ApiResponse::show('Sync-in setting', [
            'enabled' => SettingsHelper::isFeatureEnabled(self::KEY),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (! RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('Only Super admin users can change the sync-in setting.');
        }

        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        $enabled = (bool) $validator->validated()['enabled'];

        $enabled
            ? SettingsHelper::enableFeature(self::KEY)
            : SettingsHelper::disableFeature(self::KEY);

        return ApiResponse::update('Sync-in setting updated successfully.', [
            'enabled' => $enabled,
        ]);
    }
}
