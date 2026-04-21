<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\FeatureHelper;
use App\Http\Controllers\Controller;
use App\Models\Customers\Sale;
use App\Helpers\SettingsHelper;
use App\Models\Setting;
use App\Services\Currency\CurrencyService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

class SalePdfController extends Controller
{
    /**
     * Generate and download/stream sale invoice PDF
     *
     * @param Sale $sale
     * @param string $action 'download' or 'stream'
     * @return Response
     */
    public function generateInvoice(Sale $sale, string $action = 'download')
    {
        try {
            // Load all required relationships
            $sale->load([
                'customer',
                'currency',
                'salesperson',
                'warehouse',
                'items.item'
            ]);

            // Get company data from settings
            $companyData = $this->getCompanyData();

            $isMultiCurrency = FeatureHelper::isMultiCurrency();
            // Calculate totals for items
            $totalVolume = $sale->items->sum('total_volume_cbm');
            $totalWeight = $sale->items->sum('total_weight_kg');
            // $calculatedSubTotal = $sale->items->sum('total_net_sell_price');

            // Resolve local currency and invoice display settings (single query via getGroup)
            $localCurrency    = CurrencyService::getLocalCurrency();
            $invoiceGroup     = Setting::getGroup('invoice');
            $notLocalCurrency = ($sale->currency->code ?? '') !== ($localCurrency?->code ?? '');
            $invoiceSettings  = [
                'local_currency_code'       => $localCurrency?->code,
                'local_currency_symbol'     => $localCurrency?->symbol,
                'local_currency_direction'  => $localCurrency?->symbol_position,
                'show_local_currency_tax'   => $notLocalCurrency && ($invoiceGroup['show_local_currency_tax'] ?? false),
                'show_local_currency_total' => $notLocalCurrency && ($invoiceGroup['show_local_currency_total'] ?? false),
                'show_note_1'               => $invoiceGroup['show_note_1'] ?? true,
                'show_note_2'               => $invoiceGroup['show_note_2'] ?? true,
                'is_multi_currency'   => $isMultiCurrency,
            ];

            // Generate catalog QR code based on per-prefix show setting and catalog link
            $catalogQrCodeBase64 = null;
            $catalogGroup        = Setting::getGroup('item_catalog');
            $isInv               = $sale->prefix === Sale::TAXPREFIX;
            $showKey             = $isInv ? 'inv_show_qrcode' : 'inx_show_qrcode';
            $linkKey             = $isInv ? 'inv_catalog_link' : 'inx_catalog_link';
            if (!empty($catalogGroup[$showKey])) {
                $catalogLink = $catalogGroup[$linkKey] ?? null;
                if (!empty($catalogLink)) {
                    $result              = (new PngWriter())->write(new QrCode($catalogLink));
                    $catalogQrCodeBase64 = base64_encode($result->getString());
                }
            }

            // Generate QR code for customer Google Maps location (controlled per prefix)
            $qrCodeBase64 = null;
            $googleMapQrKey = $isInv ? 'inv_show_google_map_qrcode' : 'inx_show_google_map_qrcode';
            if (!empty($invoiceGroup[$googleMapQrKey])) {
                $mapsUrl = null;
                if (!empty($sale->customer->google_map)) {
                    $mapsUrl = $sale->customer->google_map;
                } elseif (!empty($sale->customer->gps_coordinates)) {
                    $coords = array_map('trim', explode(',', $sale->customer->gps_coordinates));
                    if (count($coords) === 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
                        $mapsUrl = 'https://www.google.com/maps?q=' . $coords[0] . ',' . $coords[1];
                    }
                }
                if ($mapsUrl) {
                    $result       = (new PngWriter())->write(new QrCode($mapsUrl));
                    $qrCodeBase64 = base64_encode($result->getString());
                }
            }

            // Prepare data for the view
            $data = [
                'sale'                => $sale,
                'company'             => $companyData,
                'totalVolume'         => $totalVolume,
                'totalWeight'         => $totalWeight,
                'invoiceSettings'     => $invoiceSettings,
                'qrCodeBase64'        => $qrCodeBase64,
                'catalogQrCodeBase64' => $catalogQrCodeBase64,
                'catalogLabel'        => $catalogGroup[$isInv ? 'inv_catalog_label' : 'inx_catalog_label'] ?? null,
                // 'calculatedSubTotal' => $calculatedSubTotal,
            ];
           
            // Render the Blade view to HTML
            $html = view('pdfs.sale-invoice', $data)->render();

            // Create mPDF instance with optimized margins
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 10,        // Reduced from 15
                'margin_right' => 10,       // Reduced from 15
                'margin_top' => 10,         // Reduced from 15
                'margin_bottom' => 15,      // Reduced from 25
                'margin_header' => 8,       // Reduced from 10
                'margin_footer' => 8,       // Reduced from 10
            ]);

            // Set footer with company info, invoice code and page numbers
            $invoiceCode = $sale->prefix . '-' . $sale->code;
            $companyFooterParts = [];
            if (!empty($companyData['address'])) $companyFooterParts[] = $companyData['address'];
            if (!empty($companyData['phone'])) $companyFooterParts[] = $companyData['phone'];
            if (!empty($companyData['email'])) $companyFooterParts[] = $companyData['email'];
            if (!empty($companyData['website'])) $companyFooterParts[] = $companyData['website'];
            $companyFooterLine = htmlspecialchars(implode(' | ', $companyFooterParts));

            $pageNumberRowHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td width="33%" style="text-align: left;">' . $invoiceCode . '</td>
                        <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                        <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                    </tr>
                </table>';

            // Regular footer (all pages): page number row only
            $mpdf->SetHTMLFooter($pageNumberRowHtml);

            // For INV with company details: define a named last-page footer and switch to it
            // at the very end of the HTML content so it only applies to the last page
            if ($sale->prefix === Sale::TAXPREFIX && !empty($companyFooterLine)) {
                $lastPageFooterHtml =
                    '<div style="text-align: center; font-size: 8pt; border-top: 2px solid #000000; padding-top: 4px; margin-bottom: 4px;">'
                    . $companyFooterLine .
                    '</div>'
                    . $pageNumberRowHtml;

                // Define named footer at start, switch to it at end of content
                $defineFooter = '<!--mpdf <htmlpagefooter name="lastpagefooter">' . $lastPageFooterHtml . '</htmlpagefooter> mpdf-->';
                $switchFooter = '<!--mpdf <sethtmlpagefooter name="lastpagefooter" page="ALL" value="1" /> mpdf-->';

                $html = str_replace('<body>', '<body>' . $defineFooter, $html);
                $html = str_replace('</body>', $switchFooter . '</body>', $html);
            }

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Generate filename
            $filename = 'invoice-' . $sale->prefix . '-' . $sale->code . '.pdf';

            // Output PDF
            if ($action === 'download') {
                return response()->streamDownload(function() use ($mpdf) {
                    echo $mpdf->Output('', 'S');
                }, $filename, [
                    'Content-Type' => 'application/pdf',
                ]);
            } else {
                // Stream (open in browser)
                return response($mpdf->Output('', 'S'), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company data from settings including logo and stamp
     *
     * @return array
     */
    private function getCompanyData(): array
    {
        $companyData = SettingsHelper::getGroup('company');

        // Get logo and stamp documents with full absolute paths for mPDF
        foreach (['logo', 'stamp'] as $field) {
            if (!empty($companyData[$field])) {
                $setting = Setting::where('group_name', 'company')
                    ->where('key_name', $field)
                    ->first();

                if ($setting && $setting->documents()->exists()) {
                    $document = $setting->documents()->latest()->first();

                    // Get absolute file path - remove 'public/' prefix if it exists in file_path
                    $filePath = $document->file_path;
                    if (str_starts_with($filePath, 'public/')) {
                        $filePath = substr($filePath, 7); // Remove 'public/' prefix
                    }

                    // Construct absolute path
                    $absolutePath = storage_path('app/public/' . $filePath);

                    // Check if file exists, if not try without 'app/public/'
                    if (!file_exists($absolutePath)) {
                        $absolutePath = storage_path($filePath);
                    }

                    $companyData[$field] = [
                        'preview_url' => $document->preview_url,
                        'path' => $absolutePath,
                        'exists' => file_exists($absolutePath),
                    ];
                }
            }
        }

        return $companyData;
    }
}
