<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by group_name
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        // Filter by group
        if ($request->has('group')) {
            $query->where('group_name', $request->group);
        }

        // Filter by data type
        if ($request->has('data_type')) {
            $query->where('data_type', $request->data_type);
        }

        // Search by key or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('key_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $settings = $query->orderBy('group_name')
                         ->orderBy('key_name')
                         ->get();

        // Group settings by group_name
        $groupedSettings = $settings->groupBy('group_name')->map(function ($groupSettings) {
            return $groupSettings->mapWithKeys(function ($setting) {
                return [
                    $setting->key_name => [
                        'value' => $setting->getCastValue(),
                        'data_type' => $setting->data_type,
                        'description' => $setting->description,
                        'is_encrypted' => $setting->is_encrypted,
                        'updated_at' => $setting->updated_at,
                    ]
                ];
            });
        });

        return ApiResponse::show('Settings retrieved successfully', $groupedSettings);
    }

    /**
     * Get settings for a specific group
     */
    public function getGroup(string $group): JsonResponse
    {
        $settings = Setting::getGroup($group);
        
        if (empty($settings)) {
            return ApiResponse::notFound("No settings found for group '{$group}'");
        }

        return ApiResponse::show("Settings for group '{$group}' retrieved successfully", $settings);
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $group, string $key): JsonResponse
    {
        $setting = Setting::where('group_name', $group)
                          ->where('key_name', $key)
                          ->first();

        if (!$setting) {
            return ApiResponse::notFound("Setting '{$group}.{$key}' not found");
        }

        $data = [
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'is_encrypted' => $setting->is_encrypted,
            'created_at' => $setting->created_at,
            'updated_at' => $setting->updated_at,
        ];

        return ApiResponse::show("Setting '{$group}.{$key}' retrieved successfully", $data);
    }

    /**
     * Update a specific setting
     */
    public function updateSetting(Request $request, string $group, string $key): JsonResponse
    {
        $request->validate([
            'value' => 'required',
            'data_type' => ['sometimes', Rule::in(Setting::getDataTypes())],
            'description' => 'sometimes|string|max:1000',
            'is_encrypted' => 'sometimes|boolean',
        ]);

        $setting = Setting::where('group_name', $group)
                          ->where('key_name', $key)
                          ->first();

        if (!$setting) {
            return ApiResponse::notFound("Setting '{$group}.{$key}' not found");
        }

        $setting->update([
            'value' => $request->value,
            'data_type' => $request->get('data_type', $setting->data_type),
            'description' => $request->get('description', $setting->description),
            'is_encrypted' => $request->get('is_encrypted', $setting->is_encrypted),
        ]);

        $data = [
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'is_encrypted' => $setting->is_encrypted,
            'updated_at' => $setting->updated_at,
        ];

        return ApiResponse::update("Setting '{$group}.{$key}' updated successfully", $data);
    }

    /**
     * Create a new setting
     */
    public function createSetting(Request $request): JsonResponse
    {
        $request->validate([
            'group_name' => 'required|string|max:50',
            'key_name' => 'required|string|max:100',
            'value' => 'required',
            'data_type' => ['required', Rule::in(Setting::getDataTypes())],
            'description' => 'sometimes|string|max:1000',
            'is_encrypted' => 'sometimes|boolean',
        ]);

        // Check if setting already exists
        $exists = Setting::where('group_name', $request->group_name)
                         ->where('key_name', $request->key_name)
                         ->exists();

        if ($exists) {
            return ApiResponse::error("Setting '{$request->group_name}.{$request->key_name}' already exists", 409);
        }

        $setting = Setting::create([
            'group_name' => $request->group_name,
            'key_name' => $request->key_name,
            'value' => $request->value,
            'data_type' => $request->data_type,
            'description' => $request->get('description'),
            'is_encrypted' => $request->get('is_encrypted', false),
        ]);

        $data = [
            'value' => $setting->getCastValue(),
            'data_type' => $setting->data_type,
            'description' => $setting->description,
            'is_encrypted' => $setting->is_encrypted,
            'created_at' => $setting->created_at,
        ];

        return ApiResponse::store("Setting '{$request->group_name}.{$request->key_name}' created successfully", $data);
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.group_name' => 'required|string|max:50',
            'settings.*.key_name' => 'required|string|max:100',
            'settings.*.value' => 'required',
            'settings.*.data_type' => ['sometimes', Rule::in(Setting::getDataTypes())],
            'settings.*.description' => 'sometimes|string|max:1000',
            'settings.*.is_encrypted' => 'sometimes|boolean',
        ]);

        $updatedSettings = [];
        $errors = [];

        foreach ($request->settings as $settingData) {
            try {
                $setting = Setting::updateOrCreate(
                    [
                        'group_name' => $settingData['group_name'],
                        'key_name' => $settingData['key_name']
                    ],
                    [
                        'value' => $settingData['value'],
                        'data_type' => $settingData['data_type'] ?? Setting::TYPE_STRING,
                        'description' => $settingData['description'] ?? null,
                        'is_encrypted' => $settingData['is_encrypted'] ?? false,
                    ]
                );

                $updatedSettings[] = [
                    'group_name' => $setting->group_name,
                    'key_name' => $setting->key_name,
                    'value' => $setting->getCastValue(),
                    'data_type' => $setting->data_type,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'group_name' => $settingData['group_name'],
                    'key_name' => $settingData['key_name'],
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
    public function deleteSetting(string $group, string $key): JsonResponse
    {
        $setting = Setting::where('group_name', $group)
                          ->where('key_name', $key)
                          ->first();

        if (!$setting) {
            return ApiResponse::notFound("Setting '{$group}.{$key}' not found");
        }

        $setting->delete();

        return ApiResponse::delete("Setting '{$group}.{$key}' deleted successfully");
    }

    /**
     * Clear settings cache
     */
    public function clearCache(): JsonResponse
    {
        Setting::clearCache();
        
        return ApiResponse::show('Settings cache cleared successfully');
    }

    /**
     * Get available data types
     */
    public function getDataTypes(): JsonResponse
    {
        return ApiResponse::show('Available data types', Setting::getDataTypes());
    }

    /**
     * Export settings
     */
    public function export(Request $request): JsonResponse
    {
        $query = Setting::query();

        if ($request->has('groups')) {
            $groups = is_array($request->groups) ? $request->groups : explode(',', $request->groups);
            $query->whereIn('group_name', $groups);
        }

        $settings = $query->get()->map(function ($setting) {
            return [
                'group_name' => $setting->group_name,
                'key_name' => $setting->key_name,
                'value' => $setting->value, // Export raw value, not cast
                'data_type' => $setting->data_type,
                'description' => $setting->description,
                'is_encrypted' => $setting->is_encrypted,
            ];
        });

        return ApiResponse::show('Settings exported successfully', $settings);
    }

    /**
     * Import settings
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'overwrite' => 'sometimes|boolean',
        ]);

        $imported = [];
        $skipped = [];
        $errors = [];
        $overwrite = $request->get('overwrite', false);

        foreach ($request->settings as $settingData) {
            try {
                $exists = Setting::where('group_name', $settingData['group_name'])
                                ->where('key_name', $settingData['key_name'])
                                ->exists();

                if ($exists && !$overwrite) {
                    $skipped[] = $settingData['group_name'] . '.' . $settingData['key_name'];
                    continue;
                }

                $setting = Setting::updateOrCreate(
                    [
                        'group_name' => $settingData['group_name'],
                        'key_name' => $settingData['key_name']
                    ],
                    [
                        'value' => $settingData['value'],
                        'data_type' => $settingData['data_type'] ?? Setting::TYPE_STRING,
                        'description' => $settingData['description'] ?? null,
                        'is_encrypted' => $settingData['is_encrypted'] ?? false,
                    ]
                );

                $imported[] = $settingData['group_name'] . '.' . $settingData['key_name'];
            } catch (\Exception $e) {
                $errors[] = [
                    'setting' => $settingData['group_name'] . '.' . $settingData['key_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $data = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_imported' => count($imported),
            'total_skipped' => count($skipped),
            'total_errors' => count($errors),
        ];

        return ApiResponse::store('Settings import completed', $data);
    }
}