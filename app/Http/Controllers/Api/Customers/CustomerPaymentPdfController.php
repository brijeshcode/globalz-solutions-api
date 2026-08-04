<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customers\CustomerPayment;
use App\Traits\ResolvesCompanyPdfData;
use Mpdf\Mpdf;

class CustomerPaymentPdfController extends Controller
{
    use ResolvesCompanyPdfData;

    public function generate(CustomerPayment $customerPayment, string $action = 'download')
    {
        try {
            $customerPayment->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
            ]);

            $companyData = $this->getCompanyData();

            $data = [
                'payment' => $customerPayment,
                'company' => $companyData,
            ];

            $html = view('pdfs.customer-payment-receipt', $data)->render();

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

            $paymentCode = $customerPayment->prefix . '-' . $customerPayment->code;

            $companyFooterParts = [];
            if (!empty($companyData['address'])) $companyFooterParts[] = $companyData['address'];
            if (!empty($companyData['phone']))   $companyFooterParts[] = 'Tel: ' . $companyData['phone'];
            if (!empty($companyData['email']))   $companyFooterParts[] = 'Email: ' . $companyData['email'];
            if (!empty($companyData['website'])) $companyFooterParts[] = $companyData['website'];
            $companyFooterLine = htmlspecialchars(implode(' | ', $companyFooterParts));

            $pageNumberRowHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . htmlspecialchars($paymentCode) . '</td>
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

            $filename = 'payment-' . $paymentCode . '-' . date('Y-m-d_H-i') . '.pdf';

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
}
