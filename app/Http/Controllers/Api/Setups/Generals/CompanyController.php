<?php

namespace App\Http\Controllers\Api\Setups\Generals;

use App\Http\Controllers\Controller;
use App\Helpers\SettingsHelper;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Services\Currency\CurrencyModeService;
use App\Traits\HandlesSettingFileUpload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    use HandlesSettingFileUpload;

    private const GROUP = 'company_details';

    /**
     * Keys safe to expose on the public (unauthenticated) branding endpoint.
     * Identity fields (tax_number, address, website) are intentionally omitted.
     */
    private const PUBLIC_KEYS = [
        'company_name', 'tagline', 'description',
        'primary_color', 'secondary_color',
        'contact_email', 'contact_phone',
        'logo', 'favicon',
    ];

    /**
     * Text fields writable through setCompanyDetails, with their data types.
     */
    private const TEXT_FIELDS = [
        'company_name'    => 'string',
        'tagline'         => 'string',
        'description'     => 'string',
        'primary_color'   => 'string',
        'secondary_color' => 'string',
        'contact_email'   => 'string',
        'contact_phone'   => 'string',
        'tax_number'      => 'string',
        'address'         => 'string',
        'website'         => 'string',
    ];

    /**
     * Public branding for the login page (no auth). Tenant is detected via the
     * Origin header. Only branding keys are returned — never company identity.
     */
    public function getPublicDetails(): JsonResponse
    {
        $data = $this->readGroup();

        if (empty($data)) {
            $this->createDefaults();
            $data = $this->readGroup();
        }

        $branding = array_intersect_key($data, array_flip(self::PUBLIC_KEYS));
        $branding = $this->resolveDocumentUrls($branding, ['logo', 'favicon']);

        // Append currency settings so the frontend has them on first load.
        $branding['currency'] = CurrencyModeService::getSettings();

        return ApiResponse::show('Company Details', $branding);
    }

    /**
     * Full company details for authenticated setup screens, including identity.
     */
    public function getDetails(): JsonResponse
    {
        $data = $this->readGroup();

        if (empty($data)) {
            $this->createDefaults();
            $data = $this->readGroup();
        }

        $data = $this->resolveDocumentUrls($data, ['logo', 'favicon']);

        return ApiResponse::show('Company Details', $data);
    }

    /**
     * Create/update company details (auth required).
     */
    public function setDetails(Request $request): JsonResponse
    {
        $request->validate([
            'company_name'    => 'nullable|string|max:255',
            'tagline'         => 'nullable|string|max:255',
            'description'     => 'nullable|string|max:500',
            'primary_color'   => 'nullable|string|max:50',
            'secondary_color' => 'nullable|string|max:50',
            'contact_email'   => 'nullable|email|max:255',
            'contact_phone'   => 'nullable|string|max:50',
            'tax_number'      => 'nullable|string|max:100',
            'address'         => 'nullable|string|max:500',
            'website'         => 'nullable|url|max:255',
            'logo'            => 'nullable|file|image|max:2048',
            'favicon'         => 'nullable|file|mimes:ico,png,jpg,jpeg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $uploadResult = $this->handleSettingFileUpload(self::GROUP, $request->file('logo'), 'logo', 'System Logo');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult;
            }
        }

        if ($request->hasFile('favicon')) {
            $uploadResult = $this->handleSettingFileUpload(self::GROUP, $request->file('favicon'), 'favicon', 'System Favicon');
            if ($uploadResult instanceof JsonResponse) {
                return $uploadResult;
            }
        }

        foreach (self::TEXT_FIELDS as $field => $dataType) {
            if ($request->has($field)) {
                SettingsHelper::set(self::GROUP, $field, $request->input($field), $dataType);
            }
        }

        AttachCacheVersion::invalidate('company_details');

        return ApiResponse::index('Company details updated successfully');
    }

    /**
     * Read the group directly (no cache) to avoid cross-tenant cache issues on
     * the public endpoint.
     */
    private function readGroup(): array
    {
        return Setting::where('group_name', self::GROUP)
            ->get()
            ->mapWithKeys(fn($setting) => [$setting->key_name => $setting->getCastValue()])
            ->toArray();
    }

    /**
     * Replace stored file paths with document thumbnail/preview URLs.
     */
    private function resolveDocumentUrls(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                continue;
            }

            $setting = Setting::where('group_name', self::GROUP)
                ->where('key_name', $field)
                ->first();

            if ($setting && $setting->documents()->exists()) {
                $document = $setting->documents()->latest()->first();
                $data[$field] = [
                    'thumbnail_url' => $document->thumbnail_url,
                    'preview_url'   => $document->preview_url,
                ];
            }
        }

        return $data;
    }

    private function createDefaults(): void
    {
        $defaults = [
            'company_name'    => 'Globalz system',
            'tagline'         => 'Wholesale & Distribution',
            'description'     => 'Wholesale & Distribution, employee and expense management system',
            'primary_color'   => '#1976D2',
            'secondary_color' => '#424242',
            'contact_email'   => '',
            'contact_phone'   => '',
            'tax_number'      => '',
            'address'         => '',
            'website'         => '',
            'logo'            => '',
            'favicon'         => '',
        ];

        foreach ($defaults as $key => $value) {
            SettingsHelper::set(self::GROUP, $key, $value, 'string');
        }
    }
}
