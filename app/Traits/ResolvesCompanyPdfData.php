<?php

namespace App\Traits;

use App\Helpers\SettingsHelper;
use App\Models\Setting;

trait ResolvesCompanyPdfData
{
    /**
     * Resolve the company settings group and turn the logo/stamp document
     * references into absolute file paths mpdf can embed.
     */
    protected function getCompanyData(): array
    {
        $companyData = SettingsHelper::getGroup('company');

        foreach (['logo', 'stamp'] as $field) {
            if (!empty($companyData[$field])) {
                $setting = Setting::where('group_name', 'company')
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

                    $companyData[$field] = [
                        'preview_url' => $document->preview_url,
                        'path'        => $absolutePath,
                        'exists'      => file_exists($absolutePath),
                    ];
                }
            }
        }

        return $companyData;
    }
}
