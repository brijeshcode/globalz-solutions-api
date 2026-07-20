<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Suppliers\Purchase;
use Mpdf\Mpdf;

class PurchasePdfController extends Controller
{
    public function generatePurchase(Purchase $purchase, string $action = 'download')
    {
        try {
            $purchase->load([
                'items.item',
            ]);

            $companyData = $this->getCompanyData();

            $data = [
                'purchase' => $purchase,
                'company'  => $companyData,
            ];

            $html = view('pdfs.purchase-template-1', $data)->render();

            $mpdf = new Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_left'   => 10,
                'margin_right'  => 10,
                'margin_top'    => 10,
                'margin_bottom' => 15,
                'margin_header' => 8,
                'margin_footer' => 8,
            ]);

            $purchaseCode   = $purchase->prefix . '-' . $purchase->code;
            $pageNumberHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . $purchaseCode . '</td>
                        <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                        <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                    </tr>
                </table>';

            $mpdf->SetHTMLFooter($pageNumberHtml);
            $mpdf->WriteHTML($html);

            $filename = 'purchase-' . $purchase->prefix . '-' . $purchase->code . '.pdf';

            if ($action === 'download') {
                return response()->streamDownload(function () use ($mpdf) {
                    echo $mpdf->Output('', 'S');
                }, $filename, ['Content-Type' => 'application/pdf']);
            }

            return response($mpdf->Output('', 'S'), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getCompanyData(): array
    {
        $companyData = SettingsHelper::getGroup('company');

        foreach (['logo', 'stamp'] as $field) {
            if (!empty($companyData[$field])) {
                $setting = Setting::where('group_name', 'company')->where('key_name', $field)->first();
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
