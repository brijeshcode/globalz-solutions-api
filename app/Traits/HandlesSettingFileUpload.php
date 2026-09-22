<?php

namespace App\Traits;

use App\Helpers\SettingsHelper;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

trait HandlesSettingFileUpload
{
    /**
     * Upload a file into a settings group, storing the resulting file path as
     * the setting value and attaching the document to the setting row.
     *
     * @return bool|JsonResponse True on success, error response on failure.
     */
    protected function handleSettingFileUpload(string $groupName, $file, string $settingKey, string $title)
    {
        // Create or get the setting so we have a row to attach the document to.
        $setting = SettingsHelper::set($groupName, $settingKey, '', 'string');

        $validationErrors = $setting->validateDocumentFile($file);
        if (!empty($validationErrors)) {
            return ApiResponse::customError(
                ucfirst($settingKey) . ' validation failed: ' . implode(', ', $validationErrors),
                422
            );
        }

        $document = $setting->createDocuments([$file], [
            'type' => $settingKey,
            'title' => $title,
            'description' => $title . ' image',
        ])->first();

        if ($document) {
            SettingsHelper::set($groupName, $settingKey, $document->file_path, 'string');
            return true;
        }

        return ApiResponse::customError('Failed to upload ' . strtolower($title), 500);
    }
}
