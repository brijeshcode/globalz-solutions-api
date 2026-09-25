<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Traits\HandlesSettingFileUpload;
use Illuminate\Http\JsonResponse;

class CustomerReturnSettingsController extends Controller
{
    use HandlesSettingFileUpload;

    private const GROUP = 'customer_return';

    /** Setting keys that hold uploaded files (resolved to preview URLs on read). */
    private const FILE_KEYS = ['stamp'];

    /**
     * Default customer return settings with their data types.
     */
    private const DEFAULTS = [
        'stamp'        => ['value' => '',    'type' => Setting::TYPE_STRING],
        'show_stamp'   => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'stamp_width'  => ['value' => '150', 'type' => Setting::TYPE_STRING],
        'stamp_height' => ['value' => '100', 'type' => Setting::TYPE_STRING],
    ];

    /**
     * Get all customer return settings.
     */
    public function index(): JsonResponse
    {
        $settings = $this->resolveDocumentUrls(Setting::getGroup(self::GROUP));

        return ApiResponse::show('Customer return settings retrieved successfully', $settings);
    }

    /**
     * Update customer return settings (partial or full batch).
     */
    public function update(CustomerReturnSettingsUpdateRequest $request): JsonResponse
    {
        foreach (self::FILE_KEYS as $fileField) {
            if ($request->hasFile($fileField)) {
                $result = $this->handleSettingFileUpload(
                    self::GROUP,
                    $request->file($fileField),
                    $fileField,
                    'Customer Return ' . ucfirst($fileField)
                );
                if ($result instanceof JsonResponse) {
                    return $result;
                }
            }
        }

        foreach ($request->safe()->except(self::FILE_KEYS) as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_STRING;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = $this->resolveDocumentUrls(Setting::getGroup(self::GROUP));

        return ApiResponse::update('Customer return settings updated successfully', $updated);
    }

    /**
     * Reset customer return settings to defaults.
     */
    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = $this->resolveDocumentUrls(Setting::getGroup(self::GROUP));

        return ApiResponse::update('Customer return settings reset to defaults', $settings);
    }

    /**
     * Replace stored stamp file paths with document preview URLs.
     */
    private function resolveDocumentUrls(array $settings): array
    {
        foreach (self::FILE_KEYS as $field) {
            if (empty($settings[$field])) {
                continue;
            }

            $setting = Setting::where('group_name', self::GROUP)
                ->where('key_name', $field)
                ->first();

            if ($setting && $setting->documents()->exists()) {
                $document = $setting->documents()->latest()->first();
                $settings[$field] = [
                    'thumbnail_url' => $document->thumbnail_url,
                    'preview_url'   => $document->preview_url,
                ];
            }
        }

        return $settings;
    }
}
