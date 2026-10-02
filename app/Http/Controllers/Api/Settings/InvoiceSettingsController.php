<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerInvoiceSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use App\Traits\HandlesSettingFileUpload;
use Illuminate\Http\JsonResponse;

class InvoiceSettingsController extends Controller
{
    use HandlesSettingFileUpload;

    private const GROUP = 'invoice';

    /** Setting keys that hold uploaded files (resolved to preview URLs on read). */
    private const FILE_KEYS = ['logo', 'stamp'];

    private const AVAILABLE_TEMPLATES = [
        ['id' => 'template-1', 'name' => 'Standard',      'description' => 'Default layout'],
        ['id' => 'template-2', 'name' => 'French Style',  'description' => 'Bilingual header with ICE number'],
        ['id' => 'gst',        'name' => 'GST',           'description' => 'Indian GST invoice with CGST/SGST split by tax rate'],
    ];

    private const AVAILABLE_LANGUAGES = [
        ['id' => 'en', 'name' => 'English'],
        ['id' => 'fr', 'name' => 'French'],
    ];

    /**
     * Default invoice settings with their data types.
     */
    private const DEFAULTS = [
        'prefix_tax'       => ['value' => 'INV',       'type' => Setting::TYPE_STRING],
        'prefix_tax_free'  => ['value' => 'INX',       'type' => Setting::TYPE_STRING],
        'footer_notes'     => ['value' => '',           'type' => Setting::TYPE_STRING],
        'show_bank_details'=> ['value' => false,        'type' => Setting::TYPE_BOOLEAN],
        'due_date_type'    => ['value' => 'net_days',   'type' => Setting::TYPE_STRING],
        'note_1'           => ['value' => 'Payment in USD or Market Price.', 'type' => Setting::TYPE_STRING],
        'show_note_1'      => ['value' => true,  'type' => Setting::TYPE_BOOLEAN],
        // 'note_2'           => ['value' => 'ملاحظة : ألضريبة على ألقيمة المضافة لا تسترد بعد ثلاثة أشهر من تاريخ إصدار ألفاتورة', 'type' => Setting::TYPE_STRING],
        'note_2'           => ['value' => '', 'type' => Setting::TYPE_STRING],
        'show_note_2'      => ['value' => true,  'type' => Setting::TYPE_BOOLEAN],
        'show_local_currency_tax'        => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'show_local_currency_total'      => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'default_invoice_currency_id'    => ['value' => null,  'type' => Setting::TYPE_STRING],
        'inx_show_google_map_qrcode'     => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'inv_show_google_map_qrcode'     => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'template'                       => ['value' => 'template-1', 'type' => Setting::TYPE_STRING],
        'language'                       => ['value' => 'en',         'type' => Setting::TYPE_STRING],
        'unit_price_decimals'            => ['value' => 2,            'type' => Setting::TYPE_NUMBER],
        'total_decimals'                 => ['value' => 2,            'type' => Setting::TYPE_NUMBER],
        // Company logo/stamp shown on the printed invoice.
        'logo'         => ['value' => '',    'type' => Setting::TYPE_STRING],
        'stamp'        => ['value' => '',    'type' => Setting::TYPE_STRING],
        'show_logo'    => ['value' => true,  'type' => Setting::TYPE_BOOLEAN],
        'show_stamp'   => ['value' => false, 'type' => Setting::TYPE_BOOLEAN],
        'logo_width'   => ['value' => '200', 'type' => Setting::TYPE_STRING],
        'logo_height'  => ['value' => '80',  'type' => Setting::TYPE_STRING],
        'stamp_width'  => ['value' => '150', 'type' => Setting::TYPE_STRING],
        'stamp_height' => ['value' => '100', 'type' => Setting::TYPE_STRING],
    ];

    /**
     * Get all invoice settings.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);
        $settings = $this->resolveDocumentUrls($settings);

        $settings['available_templates'] = self::AVAILABLE_TEMPLATES;
        $settings['available_languages'] = self::AVAILABLE_LANGUAGES;

        return ApiResponse::show('Invoice settings retrieved successfully', $settings);
    }

    /**
     * Update invoice settings (partial or full batch).
     */
    public function update(CustomerInvoiceSettingsUpdateRequest $request): JsonResponse
    {
        foreach (self::FILE_KEYS as $fileField) {
            if ($request->hasFile($fileField)) {
                $result = $this->handleSettingFileUpload(
                    self::GROUP,
                    $request->file($fileField),
                    $fileField,
                    'Invoice ' . ucfirst($fileField)
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

        return ApiResponse::update('Invoice settings updated successfully', $updated);
    }

    /**
     * Reset invoice settings to defaults.
     */
    public function reset(): JsonResponse
    {
        foreach (self::DEFAULTS as $key => $config) {
            Setting::set(self::GROUP, $key, $config['value'], $config['type']);
        }

        $settings = $this->resolveDocumentUrls(Setting::getGroup(self::GROUP));

        return ApiResponse::update('Invoice settings reset to defaults', $settings);
    }

    /**
     * Replace stored logo/stamp file paths with document preview URLs.
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
