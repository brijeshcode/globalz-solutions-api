<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    /**
     * Get all settings for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = UserSetting::getAllForUser($user->id);

        return ApiResponse::show('User settings retrieved successfully', $settings);
    }

    /**
     * Get a specific setting for the authenticated user
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $user = $request->user();
        $setting = UserSetting::where('user_id', $user->id)
                             ->where('key_name', $key)
                             ->first();

        if (!$setting) {
            return ApiResponse::notFound("Setting '{$key}' not found");
        }

        $data = [
            'key' => $setting->key_name,
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'updated_at' => $setting->updated_at,
        ];

        return ApiResponse::show("Setting '{$key}' retrieved successfully", $data);
    }

    /**
     * Update or create a setting for the authenticated user
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'key_name' => 'required|string|max:100',
            'value' => 'required',
            'data_type' => ['sometimes', Rule::in(UserSetting::getDataTypes())],
            'description' => 'sometimes|string|max:1000',
        ]);

        $user = $request->user();
        
        $setting = UserSetting::set(
            $user->id,
            $request->key_name,
            $request->value,
            $request->get('data_type', UserSetting::TYPE_STRING),
            $request->get('description')
        );

        $data = [
            'key' => $setting->key_name,
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'updated_at' => $setting->updated_at,
        ];

        return ApiResponse::store("Setting '{$request->key_name}' saved successfully", $data);
    }

    /**
     * Update a specific setting for the authenticated user
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'value' => 'required',
            'data_type' => ['sometimes', Rule::in(UserSetting::getDataTypes())],
            'description' => 'sometimes|string|max:1000',
        ]);

        $user = $request->user();
        
        $setting = UserSetting::where('user_id', $user->id)
                             ->where('key_name', $key)
                             ->first();

        if (!$setting) {
            return ApiResponse::notFound("Setting '{$key}' not found");
        }

        $setting->update([
            'value' => $request->value,
            'data_type' => $request->get('data_type', $setting->data_type),
            'description' => $request->get('description', $setting->description),
        ]);

        $data = [
            'key' => $setting->key_name,
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'updated_at' => $setting->updated_at,
        ];

        return ApiResponse::update("Setting '{$key}' updated successfully", $data);
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key_name' => 'required|string|max:100',
            'settings.*.value' => 'required',
            'settings.*.data_type' => ['sometimes', Rule::in(UserSetting::getDataTypes())],
            'settings.*.description' => 'sometimes|string|max:1000',
        ]);

        $user = $request->user();
        $updatedSettings = [];
        $errors = [];

        foreach ($request->settings as $settingData) {
            try {
                $setting = UserSetting::set(
                    $user->id,
                    $settingData['key_name'],
                    $settingData['value'],
                    $settingData['data_type'] ?? UserSetting::TYPE_STRING,
                    $settingData['description'] ?? null
                );

                $updatedSettings[] = [
                    'key' => $setting->key_name,
                    'value' => $setting->getCastValue(),
                    'data_type' => $setting->data_type,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'key' => $settingData['key_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $data = [
            'updated' => $updatedSettings,
            'errors' => $errors,
            'total_updated' => count($updatedSettings),
            'total_errors' => count($errors),
        ];

        return ApiResponse::update('Settings updated', $data);
    }

    /**
     * Delete a setting
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $user = $request->user();
        $deleted = UserSetting::remove($user->id, $key);

        if (!$deleted) {
            return ApiResponse::notFound("Setting '{$key}' not found");
        }

        return ApiResponse::delete("Setting '{$key}' deleted successfully");
    }

    /**
     * Reset user settings to default
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Delete all user settings
        UserSetting::where('user_id', $user->id)->delete();

        // Set default settings
        $defaultSettings = [
            'theme' => ['value' => 'light', 'data_type' => UserSetting::TYPE_STRING],
            'layout' => ['value' => 'sidebar', 'data_type' => UserSetting::TYPE_STRING],
            'language' => ['value' => 'en', 'data_type' => UserSetting::TYPE_STRING],
            'timezone' => ['value' => 'UTC', 'data_type' => UserSetting::TYPE_STRING],
            'notifications_enabled' => ['value' => true, 'data_type' => UserSetting::TYPE_BOOLEAN],
        ];

        UserSetting::setMultiple($user->id, $defaultSettings);

        return ApiResponse::update('User settings reset to defaults successfully');
    }

    /**
     * Get user's theme settings
     */
    public function getTheme(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $themeSettings = [
            'theme' => UserSetting::get($user->id, 'theme', 'light'),
            'layout' => UserSetting::get($user->id, 'layout', 'sidebar'),
            'sidebar_collapsed' => UserSetting::get($user->id, 'sidebar_collapsed', false),
            'color_scheme' => UserSetting::get($user->id, 'color_scheme', 'default'),
        ];

        return ApiResponse::show('Theme settings retrieved successfully', $themeSettings);
    }

    /**
     * Update user's theme settings
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $request->validate([
            'theme' => 'sometimes|string|in:light,dark,auto',
            'layout' => 'sometimes|string|in:sidebar,topbar,minimal',
            'sidebar_collapsed' => 'sometimes|boolean',
            'color_scheme' => 'sometimes|string|in:default,blue,green,purple,red',
        ]);

        $user = $request->user();
        $updated = [];

        foreach ($request->only(['theme', 'layout', 'sidebar_collapsed', 'color_scheme']) as $key => $value) {
            if ($request->has($key)) {
                $dataType = $key === 'sidebar_collapsed' ? UserSetting::TYPE_BOOLEAN : UserSetting::TYPE_STRING;
                UserSetting::set($user->id, $key, $value, $dataType);
                $updated[$key] = $value;
            }
        }

        return ApiResponse::update('Theme settings updated successfully', $updated);
    }

    /**
     * Get user's notification preferences
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $preferences = [
            'notifications_enabled' => UserSetting::get($user->id, 'notifications_enabled', true),
            'email_notifications' => UserSetting::get($user->id, 'email_notifications', true),
            'push_notifications' => UserSetting::get($user->id, 'push_notifications', false),
            'low_stock_alerts' => UserSetting::get($user->id, 'low_stock_alerts', true),
            'order_updates' => UserSetting::get($user->id, 'order_updates', true),
        ];

        return ApiResponse::show('Notification preferences retrieved successfully', $preferences);
    }

    /**
     * Update user's notification preferences
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'notifications_enabled' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'low_stock_alerts' => 'sometimes|boolean',
            'order_updates' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $updated = [];

        foreach ($request->only(['notifications_enabled', 'email_notifications', 'push_notifications', 'low_stock_alerts', 'order_updates']) as $key => $value) {
            if ($request->has($key)) {
                UserSetting::set($user->id, $key, $value, UserSetting::TYPE_BOOLEAN);
                $updated[$key] = $value;
            }
        }

        return ApiResponse::update('Notification preferences updated successfully', $updated);
    }

    /**
     * Admin: Get settings for a specific user
     */
    public function getForUser(int $userId): JsonResponse
    {
        // Add authorization check here if needed
        $settings = UserSetting::getAllForUser($userId);

        return ApiResponse::show("Settings for user {$userId} retrieved successfully", $settings);
    }

    /**
     * Admin: Update settings for a specific user
     */
    public function updateForUser(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key_name' => 'required|string|max:100',
            'settings.*.value' => 'required',
            'settings.*.data_type' => ['sometimes', Rule::in(UserSetting::getDataTypes())],
        ]);

        // Add authorization check here if needed

        $updatedSettings = [];
        foreach ($request->settings as $settingData) {
            $setting = UserSetting::set(
                $userId,
                $settingData['key_name'],
                $settingData['value'],
                $settingData['data_type'] ?? UserSetting::TYPE_STRING
            );

            $updatedSettings[] = [
                'key' => $setting->key_name,
                'value' => $setting->getCastValue(),
                'data_type' => $setting->data_type,
            ];
        }

        return ApiResponse::update("Settings for user {$userId} updated successfully", $updatedSettings);
    }
}