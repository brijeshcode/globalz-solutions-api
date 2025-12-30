<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customers\Sale;
use App\Helpers\SettingsHelper;
use App\Models\Setting;
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

            // Calculate totals for items
            $totalVolume = $sale->items->sum('total_volume_cbm');
            $totalWeight = $sale->items->sum('total_weight_kg');
            $calculatedSubTotal = $sale->items->sum('total_net_sell_price');

            // Prepare data for the view
            $data = [
                'sale' => $sale,
                'company' => $companyData,
                'totalVolume' => $totalVolume,
                'totalWeight' => $totalWeight,
                'calculatedSubTotal' => $calculatedSubTotal,
            ];

            // Render the Blade view to HTML
            $html = view('pdfs.sale-invoice', $data)->render();

            // Create mPDF instance
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 25,
                'margin_header' => 10,
                'margin_footer' => 10,
            ]);

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
