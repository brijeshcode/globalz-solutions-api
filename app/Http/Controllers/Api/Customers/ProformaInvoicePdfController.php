<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Customers\ProformaInvoice;
use App\Models\Setting;
use App\Services\Currency\CurrencyService;
use App\Traits\ResolvesCompanyPdfData;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

class ProformaInvoicePdfController extends Controller
{
    use ResolvesCompanyPdfData;

    public function generateInvoice(ProformaInvoice $proformaInvoice, string $action = 'download')
    {
        try {
            $proformaInvoice->load([
                'customer',
                'currency',
                'salesperson',
                'warehouse',
                'items.item',
            ]);

            $companyData   = $this->getCompanyData();
            $localCurrency = CurrencyService::getLocalCurrency();
            $invoiceGroup  = Setting::getGroup('invoice');

            $notLocalCurrency = ($proformaInvoice->currency->code ?? '') !== ($localCurrency?->code ?? '');

            $invoiceSettings = [
                'local_currency_code'       => $localCurrency?->code,
                'local_currency_symbol'     => $localCurrency?->symbol,
                'local_currency_direction'  => $localCurrency?->symbol_position,
                'show_local_currency_tax'   => $notLocalCurrency && ($invoiceGroup['show_local_currency_tax'] ?? false),
                'show_local_currency_total' => $notLocalCurrency && ($invoiceGroup['show_local_currency_total'] ?? false),
                'show_note_1'               => $invoiceGroup['show_note_1'] ?? true,
                'show_note_2'               => $invoiceGroup['show_note_2'] ?? true,
                'unit_price_decimals'       => min(max((int) ($invoiceGroup['unit_price_decimals'] ?? 2), 0), 6),
                'total_decimals'            => min(max((int) ($invoiceGroup['total_decimals'] ?? 2), 0), 6),
            ];

            $language = $invoiceGroup['language'] ?? 'en';

            $data = [
                'proforma'        => $proformaInvoice,
                'company'         => $companyData,
                'totalVolume'     => $proformaInvoice->items->sum('total_volume_cbm'),
                'totalWeight'     => $proformaInvoice->items->sum('total_weight_kg'),
                'invoiceSettings' => $invoiceSettings,
            ];

            $previousLocale = app()->getLocale();
            app()->setLocale($language);
            $html = view('pdfs.proforma-invoice-template-1', $data)->render();
            app()->setLocale($previousLocale);

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

            $invoiceCode    = $proformaInvoice->prefix . '-' . $proformaInvoice->code;
            $pageNumberHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . $invoiceCode . '</td>
                        <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                        <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                    </tr>
                </table>';

            $mpdf->SetHTMLFooter($pageNumberHtml);
            $mpdf->WriteHTML($html);

            $filename = 'proforma-' . $proformaInvoice->prefix . '-' . $proformaInvoice->code . '.pdf';

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
