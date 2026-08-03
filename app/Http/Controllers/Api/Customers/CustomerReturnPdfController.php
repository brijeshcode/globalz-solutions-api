<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Customers\CustomerReturn;
use App\Models\Setting;
use Mpdf\Mpdf;

class CustomerReturnPdfController extends Controller
{
    public function generate(CustomerReturn $customerReturn, string $action = 'download')
    {
        try {
            $customerReturn->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'items.item:id,short_name,code,description',
                'items.item.itemUnit:id,name,symbol',
                'items.item.taxCode:id,name,code,description,tax_percent',
                'items.sale:id,code,date,prefix',
                'createdBy:id,name',
            ]);

            $companyData = $this->getCompanyData();

            $data = [
                'customerReturn' => $customerReturn,
                'company'        => $companyData,
            ];

            $html = view('pdfs.customer-return-receipt', $data)->render();

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

            $returnCode = $customerReturn->prefix . $customerReturn->code;

            $companyFooterParts = [];
            if (!empty($companyData['address'])) $companyFooterParts[] = $companyData['address'];
            if (!empty($companyData['phone']))   $companyFooterParts[] = 'Tel: ' . $companyData['phone'];
            if (!empty($companyData['email']))   $companyFooterParts[] = 'Email: ' . $companyData['email'];
            if (!empty($companyData['website'])) $companyFooterParts[] = $companyData['website'];
            $companyFooterLine = htmlspecialchars(implode(' | ', $companyFooterParts));

            $pageNumberRowHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . htmlspecialchars($returnCode) . '</td>
                        <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                        <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                    </tr>
                </table>';

            if (!empty($companyFooterLine)) {
                $footerHtml =
                    '<div style="text-align: center; font-size: 8pt; border-top: 2px solid #000000; padding-top: 4px; margin-bottom: 4px;">'
                    . $companyFooterLine .
                    '</div>'
                    . $pageNumberRowHtml;
            } else {
                $footerHtml = $pageNumberRowHtml;
            }

            $mpdf->SetHTMLFooter($footerHtml);
            $mpdf->WriteHTML($html);

            $filename = 'return-' . $returnCode . '-' . date('Y-m-d_H-i') . '.pdf';

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
