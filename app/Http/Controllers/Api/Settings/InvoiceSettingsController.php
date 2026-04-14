<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerInvoiceSettingsUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSettingsController extends Controller
{
    private const GROUP = 'invoice';

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
    ];

    /**
     * Get all invoice settings.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::getGroup(self::GROUP);

        return ApiResponse::show('Invoice settings retrieved successfully', $settings);
    }

    /**
     * Update invoice settings (partial or full batch).
     */
    public function update(CustomerInvoiceSettingsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $dataType = self::DEFAULTS[$key]['type'] ?? Setting::TYPE_STRING;
            Setting::set(self::GROUP, $key, $value, $dataType);
        }

        $updated = Setting::getGroup(self::GROUP);

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

        $settings = Setting::getGroup(self::GROUP);

        return ApiResponse::update('Invoice settings reset to defaults', $settings);
    }
}
