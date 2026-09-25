<?php

namespace App\Traits;

use App\Helpers\SettingsHelper;
use App\Models\Setting;

trait ResolvesCompanyPdfData
{
    /**
     * Build the `$company` array the PDF blades expect, sourcing identity from
     * the `company_details` group and print branding (logo/stamp + toggles)
     * from the `invoice` group. Logo/stamp document references are resolved to
     * absolute file paths mpdf can embed.
     *
     * `$stampGroup` lets a document pull its stamp from a different settings
     * group (e.g. customer returns use their own stamp) while keeping the logo
     * and identity from the shared groups.
     */
    protected function getCompanyData(string $stampGroup = 'invoice'): array
    {
        $details = SettingsHelper::getGroup('company_details');
        $invoice = SettingsHelper::getGroup('invoice');
        $stamp   = $stampGroup === 'invoice' ? $invoice : SettingsHelper::getGroup($stampGroup);

        $company = [
            'name'         => $details['company_name']  ?? null,
            'tax_number'   => $details['tax_number']    ?? null,
            'address'      => $details['address']       ?? null,
            'phone'        => $details['contact_phone'] ?? null,
            'email'        => $details['contact_email'] ?? null,
            'website'      => $details['website']       ?? null,
            'show_logo'    => $invoice['show_logo']    ?? false,
            'show_stamp'   => $stamp['show_stamp']     ?? false,
            'logo_width'   => $invoice['logo_width']   ?? null,
            'logo_height'  => $invoice['logo_height']  ?? null,
            'stamp_width'  => $stamp['stamp_width']    ?? null,
            'stamp_height' => $stamp['stamp_height']   ?? null,
            'logo'         => $invoice['logo']         ?? null,
            'stamp'        => $stamp['stamp']          ?? null,
        ];

        $fieldGroups = ['logo' => 'invoice', 'stamp' => $stampGroup];
        foreach ($fieldGroups as $field => $group) {
            if (empty($company[$field])) {
                continue;
            }

            $setting = Setting::where('group_name', $group)
                ->where('key_name', $field)
                ->first();

            if ($setting && $setting->documents()->exists()) {
                $document = $setting->documents()->latest()->first();

                $filePath = $document->file_path;
                if (str_starts_with($filePath, 'public/')) {
                    $filePath = substr($filePath, 7);
                }

                $absolutePath = storage_path('app/public/' . $filePath);
                if (!file_exists($absolutePath)) {
                    $absolutePath = storage_path($filePath);
                }

                $company[$field] = [
                    'preview_url' => $document->preview_url,
                    'path'        => $absolutePath,
                    'exists'      => file_exists($absolutePath),
                ];
            }
        }

        return $company;
    }
}
