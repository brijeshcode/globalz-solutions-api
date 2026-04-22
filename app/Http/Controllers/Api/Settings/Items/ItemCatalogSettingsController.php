<?php

namespace App\Http\Controllers\Api\Settings\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\Items\ItemCatalogSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemCatalogSettingsController extends Controller
{
    private const GROUP = 'item_catalog';

    private const DEFAULTS = [
        'inv_show_qrcode'   => ['value' => false,      'type' => Setting::TYPE_BOOLEAN],
        'inv_external_link' => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inv_internal_link' => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inv_active_link'   => ['value' => 'internal', 'type' => Setting::TYPE_STRING],
        'inv_label'         => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inv_file'          => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inx_show_qrcode'   => ['value' => false,      'type' => Setting::TYPE_BOOLEAN],
        'inx_external_link' => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inx_internal_link' => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inx_active_link'   => ['value' => 'internal', 'type' => Setting::TYPE_STRING],
        'inx_label'         => ['value' => null,       'type' => Setting::TYPE_STRING],
        'inx_file'          => ['value' => null,       'type' => Setting::TYPE_STRING],
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

    public function uploadCatalog(Request $request): JsonResponse
    {
        if (! RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only super admin can upload catalog files.', 403);
        }

        $request->validate([
            'type' => 'required|string|in:inv,inx',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,gif,webp|max:20480',
        ]);

        $type     = $request->input('type');
        $file     = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $folder    = $this->getCatalogFolder();
        $filePath  = "{$folder}/{$fileName}";
        $linkKey   = "{$type}_catalog_file";

        $stored = $file->storeAs($folder, $fileName, 'public');
        if (! $stored) {
            return ApiResponse::customError('Failed to store catalog file.', 500);
        }

        $publicUrl = asset("storage/{$filePath}");

        Setting::set(self::GROUP, $linkKey, $publicUrl, Setting::TYPE_STRING);

        return ApiResponse::store("Catalog file uploaded successfully", [
            'type'       => $type,
            'url'        => $publicUrl,
            'file_name'  => $fileName,
            'setting_key'=> $linkKey,
        ]);
    }

    public function deleteCatalog(Request $request): JsonResponse
    {
        if (! RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only super admin can delete catalog files.', 403);
        }

        $request->validate([
            'type' => 'required|string|in:inv,inx',
        ]);

        $type           = $request->input('type');
        $linkKey        = "{$type}_catalog_file";
        $existingUrl    = Setting::get(self::GROUP, $linkKey);

        if ($existingUrl) {
            $existingPath = $this->urlToStoragePath($existingUrl);
            if ($existingPath && Storage::disk('public')->exists($existingPath)) {
                Storage::disk('public')->delete($existingPath);
            }
        }

        Setting::set(self::GROUP, $linkKey, null, Setting::TYPE_STRING);

        return ApiResponse::delete("Catalog file deleted successfully");
    }

    private function getCatalogFolder(): string
    {
        $tenant = Tenant::current();

        return $tenant
            ? "catalogs/{$tenant->tenant_key}"
            : 'catalogs';
    }

    private function urlToStoragePath(string $url): ?string
    {
        $storageUrl = rtrim(asset('storage'), '/');

        if (str_starts_with($url, $storageUrl)) {
            return ltrim(substr($url, strlen($storageUrl)), '/');
        }

        return null;
    }
}
