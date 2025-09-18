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
            $uploadResult = $this->handleFileUpload($request->file('logo'), 'logo', 'Company Logo');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult; // Return error response
            }
        }

        if ($request->hasFile('stamp')) {
            $uploadResult = $this->handleFileUpload($request->file('stamp'), 'stamp', 'Company Stamp');
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
            'tax_number' => 'string'
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
     * Handle file upload for company documents
     */
    private function handleFileUpload($file, string $settingKey, string $title)
    {
        // First, create or get the setting to get a proper ID
        $setting = SettingsHelper::set('company', $settingKey, '', 'string');

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
            SettingsHelper::set('company', $settingKey, $document->file_path, 'string');
            return true; // Success
        }

        return ApiResponse::customError('Failed to upload ' . strtolower($title), 500);
    }
}
