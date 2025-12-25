<?php

namespace App\Http\Controllers\Api\Setups\Generals;

use App\Http\Controllers\Controller;
use App\Helpers\SettingsHelper;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Traits\HasDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    use HasDocuments;

    private const TENANT_DETAILS_GROUP = 'tenant_details';

    public function get(): JsonResponse
    {
        $companyData = SettingsHelper::getGroup('company');

        // For logo and stamp, get the actual document data instead of just file path
        foreach (['logo', 'stamp'] as $field) {
            if (!empty($companyData[$field])) {
                $setting = Setting::where('group_name', 'company')
                    ->where('key_name', $field)
                    ->first();

                if ($setting && $setting->documents()->exists()) {
                    $document = $setting->documents()->latest()->first();
                    $companyData[$field] = [
                        // 'file_path' => $companyData[$field],
                        // 'id' => $document->id,
                        // 'original_name' => $document->original_name,
                        // 'file_name' => $document->file_name,
                        'thumbnail_url' => $document->thumbnail_url,
                        // 'download_url' => $document->download_url,
                        'preview_url' => $document->preview_url,
                    ];
                }
            }
        }

        return ApiResponse::show('Company data', $companyData);
    }

    public function set(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|string|max:100',
            'logo' => 'nullable|file|image|max:2048',
            'stamp' => 'nullable|file|image|max:2048'
        ]);

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $uploadResult = $this->handleSettingFileUpload('company', $request->file('logo'), 'logo', 'Company Logo');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult; // Return error response
            }
        }

        if ($request->hasFile('stamp')) {
            $uploadResult = $this->handleSettingFileUpload('company', $request->file('stamp'), 'stamp', 'Company Stamp');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult; // Return error response
            }
        }

        // Handle text fields
        $companyFields = [
            'name' => 'string',
            'address' => 'string',
            'phone' => 'string',
            'email' => 'string',
            'website' => 'string',
            'tax_number' => 'string',
            'show_logo' => 'boolean',
            'show_stamp' => 'boolean',
            'logo_width' => 'string',
            'logo_height' => 'string',
            'stamp_width' => 'string',
            'stamp_height' => 'string',
        ];

        foreach ($companyFields as $field => $dataType) {
            if ($request->has($field)) {
                SettingsHelper::set('company', $field, $request->input($field), $dataType);
            }
        }

        return ApiResponse::index('Company data updated successfully');
    }

    public function getSelected(Request $request): JsonResponse
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'in:name,address,phone,email,website,tax_number,logo,stamp'
        ]);

        $selectedData = [];
        foreach ($request->input('fields') as $field) {
            $value = SettingsHelper::get('company', $field);

            // For logo and stamp, get the actual document data instead of just file path
            if (in_array($field, ['logo', 'stamp']) && $value) {
                $setting = Setting::where('group_name', 'company')
                    ->where('key_name', $field)
                    ->first();

                if ($setting && $setting->documents()->exists()) {
                    $document = $setting->documents()->latest()->first();
                    $selectedData[$field] = [
                        'file_path' => $value,
                        'id' => $document->id,
                        'original_name' => $document->original_name,
                        'file_name' => $document->file_name,
                        'thumbnail_url' => $document->thumbnail_url,
                        'download_url' => $document->download_url,
                        'preview_url' => $document->preview_url,
                    ];
                } else {
                    $selectedData[$field] = $value;
                }
            } else {
                $selectedData[$field] = $value;
            }
        }

        return ApiResponse::show('Selected company data', $selectedData);
    }

    /**
     * Handle file upload for settings (company or tenant_details)
     * Unified method for uploading files to any settings group
     *
     * @param string $groupName The settings group (e.g., 'company', 'tenant_details')
     * @param mixed $file The uploaded file
     * @param string $settingKey The setting key (e.g., 'logo', 'stamp', 'favicon')
     * @param string $title The document title
     * @return bool|JsonResponse True on success, error response on failure
     */
    private function handleSettingFileUpload(string $groupName, $file, string $settingKey, string $title)
    {
        // First, create or get the setting to get a proper ID
        $setting = SettingsHelper::set($groupName, $settingKey, '', 'string');

        // Validate file
        $validationErrors = $setting->validateDocumentFile($file);
        if (!empty($validationErrors)) {
            return ApiResponse::customError(
                ucfirst($settingKey) . ' validation failed: ' . implode(', ', $validationErrors),
                422
            );
        }

        // Upload file
        $document = $setting->createDocuments([$file], [
            'type' => $settingKey,
            'title' => $title,
            'description' => $title . ' image'
        ])->first();

        if ($document) {
            // Update the setting with the actual file path
            SettingsHelper::set($groupName, $settingKey, $document->file_path, 'string');
            return true; // Success
        }

        return ApiResponse::customError('Failed to upload ' . strtolower($title), 500);
    }

    /**
     * Get tenant details for branding (public - no auth required)
     * Used for login page branding
     * Tenant is automatically detected via Origin header
     */
    public function getTenantDetails(): JsonResponse
    {
        $tenantData = SettingsHelper::getGroup(self::TENANT_DETAILS_GROUP);

        // For logo and favicon, get the actual document data with URLs
        foreach (['logo', 'favicon'] as $field) {
            if (!empty($tenantData[$field])) {
                $setting = Setting::where('group_name', self::TENANT_DETAILS_GROUP)
                    ->where('key_name', $field)
                    ->first();

                if ($setting && $setting->documents()->exists()) {
                    $document = $setting->documents()->latest()->first();
                    $tenantData[$field] = [
                        'thumbnail_url' => $document->thumbnail_url,
                        'preview_url' => $document->preview_url,
                    ];
                }
            }
        }

        // If no tenant details exist, create defaults
        if (empty($tenantData)) {
            $this->createDefaultTenantDetails();
            $tenantData = SettingsHelper::getGroup(self::TENANT_DETAILS_GROUP);
        }

        return ApiResponse::show('Tenant Details', $tenantData);
    }

    /**
     * Set/update tenant details (protected - auth required)
     */
    public function setTenantDetails(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:50',
            'secondary_color' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'logo' => 'nullable|file|image|max:2048',
            'favicon' => 'nullable|file|mimes:ico,png,jpg,jpeg,webp|max:2048',
        ]);
        // Handle file uploads for logo and favicon
        if ($request->hasFile('logo')) {
            $uploadResult = $this->handleSettingFileUpload(self::TENANT_DETAILS_GROUP, $request->file('logo'), 'logo', 'System Logo');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult; // Return error response
            }
        }

        if ($request->hasFile('favicon')) {
            $uploadResult = $this->handleSettingFileUpload(self::TENANT_DETAILS_GROUP, $request->file('favicon'), 'favicon', 'System Favicon');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult; // Return error response
            }
        }

        // Handle text fields
        $tenantFields = [
            'company_name' => 'string',
            'tagline' => 'string',
            'description' => 'string',
            'primary_color' => 'string',
            'secondary_color' => 'string',
            'contact_email' => 'string',
            'contact_phone' => 'string',
        ];

        foreach ($tenantFields as $field => $dataType) {
            if ($request->has($field)) {
                SettingsHelper::set(self::TENANT_DETAILS_GROUP, $field, $request->input($field), $dataType);
            }
        }

        return ApiResponse::index('Tenant details updated successfully');
    }

    /**
     * Create default tenant details
     */
    private function createDefaultTenantDetails(): void
    {
        $defaults = [
            'company_name' => ['value' => 'Globalz Solutions - Wholesale & Distribution', 'type' => 'string'],
            'tagline' => ['value' => 'Wholesale & Distribution', 'type' => 'string'],
            'description' => ['value' => 'Wholesale & Distribution, employee and expense management system', 'type' => 'string'],
            'primary_color' => ['value' => '#1976D2', 'type' => 'string'],
            'secondary_color' => ['value' => '#424242', 'type' => 'string'],
            'contact_email' => ['value' => '', 'type' => 'string'],
            'contact_phone' => ['value' => '', 'type' => 'string'],
            'logo' => ['value' => '', 'type' => 'string'],
            'favicon' => ['value' => '', 'type' => 'string'],
        ];

        foreach ($defaults as $key => $config) {
            SettingsHelper::set(self::TENANT_DETAILS_GROUP, $key, $config['value'], $config['type']);
        }
    }

}
