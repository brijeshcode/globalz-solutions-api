<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class CustomerStatementPdfController extends Controller
{
    private const TAXFEE = [
        Sale::TAXFREEPREFIX,
        CustomerReturn::TAXFREEPREFIX,
        CustomerPayment::TAXFREEPREFIX,
        CustomerCreditDebitNote::CREDITTAXFREEPREFIX,
        CustomerCreditDebitNote::DEBITTAXFREEPREFIX,
    ];

    private const TAX = [
        Sale::TAXPREFIX,
        CustomerReturn::TAXPREFIX,
        CustomerPayment::TAXPREFIX,
        CustomerCreditDebitNote::CREDITTAXPREFIX,
        CustomerCreditDebitNote::DEBITTAXPREFIX,
    ];

    public function generate(Request $request, Customer $customer, string $type = 'all', string $action = 'download')
    {
        try {
            $customer->load('salesperson');

            $transactions = $this->buildTransactions($request, $customer, $type);
            $stats        = $this->calculateStats($transactions);
            $companyData  = $this->getCompanyData();

            $data = [
                'customer'          => $customer,
                'company'           => $companyData,
                'transactions'      => $transactions,
                'stats'             => $stats,
                'type'              => $type,
                'showCompanyHeader' => $type === 'tax',
                'fromDate'          => $request->get('from_date'),
                'toDate'            => $request->get('to_date'),
            ];

            $html = view('pdfs.customer-statement', $data)->render();

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

            $pageNumberRowHtml = '
                <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                    <tr>
                        <td style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                    </tr>
                </table>';

            $mpdf->SetHTMLFooter($pageNumberRowHtml);

            if ($type === 'tax') {
                $customerLabel      = $customer->code . ' - ' . htmlspecialchars($customer->name);
                $taxPageNumberRow   = '
                    <table width="100%" style="font-size: 9pt; border-top: 1px solid #000000; padding-top: 5px;">
                        <tr>
                            <td width="33%" style="text-align: left;">' . $customerLabel . '</td>
                            <td width="33%" style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                            <td width="33%" style="text-align: right;">' . date('Y-m-d') . '</td>
                        </tr>
                    </table>';

                $companyFooterParts = [];
                if (!empty($companyData['address'])) $companyFooterParts[] = $companyData['address'];
                if (!empty($companyData['phone']))   $companyFooterParts[] = $companyData['phone'];
                if (!empty($companyData['email']))   $companyFooterParts[] = $companyData['email'];
                if (!empty($companyData['website'])) $companyFooterParts[] = $companyData['website'];
                $companyFooterLine = htmlspecialchars(implode(' | ', $companyFooterParts));

                $lastPageFooterHtml = (!empty($companyFooterLine)
                    ? '<div style="text-align: center; font-size: 8pt; border-top: 2px solid #000000; padding-top: 4px; margin-bottom: 4px;">' . $companyFooterLine . '</div>'
                    : '') . $taxPageNumberRow;

                $defineFooter = '<!--mpdf <htmlpagefooter name="taxfooter">' . $lastPageFooterHtml . '</htmlpagefooter> mpdf-->';
                $switchFooter = '<!--mpdf <sethtmlpagefooter name="taxfooter" page="ALL" value="1" /> mpdf-->';

                $html = str_replace('<body>', '<body>' . $defineFooter, $html);
                $html = str_replace('</body>', $switchFooter . '</body>', $html);
            }

            $mpdf->WriteHTML($html);

            $filename = $customer->name . '-' . $customer->code . '-' . date('Y-m-d_H-i') . '.pdf';

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

    private function buildTransactions(Request $request, Customer $customer, string $type): \Illuminate\Support\Collection
    {
        $childIds    = $customer->children()->pluck('id')->toArray();
        $isParent    = !empty($childIds);
        $customerIds = $isParent ? array_merge([$customer->id], $childIds) : [$customer->id];

        $all = collect()
            ->concat($this->getCreditDebitNotes($request, $customerIds))
            ->concat($this->getSales($request, $customerIds))
            ->concat($this->getPayments($request, $customerIds))
            ->concat($this->getReturns($request, $customerIds));

        if ($type === 'tax') {
            $all = $all->whereIn('prefix', self::TAX)->values();
        } elseif ($type === 'non-tax') {
            $all = $all->whereIn('prefix', self::TAXFEE)->values();
        }

        $balance = 0;
        return $all->sortBy('date')->values()->map(function ($t) use (&$balance) {
            $balance       += $t['credit'] - $t['debit'];
            $t['balance']   = $balance;
            return $t;
        })->sortByDesc('date')->values();
    }

    private function getCreditDebitNotes(Request $request, array $customerIds): \Illuminate\Support\Collection
    {
        $query = CustomerCreditDebitNote::query()
            ->select('id', 'code', 'prefix', 'date', 'type', 'amount_usd', 'note', 'customer_id', 'created_at')
            ->whereIn('customer_id', $customerIds);

        $this->applyDateFilters($query, $request);

        return $query->get()->map(fn ($item) => [
            'id'               => $item->id,
            'code'             => $item->prefix . $item->code,
            'prefix'           => $item->prefix,
            'type'             => $item->type === 'credit' ? 'Credit Note' : 'Debit Note',
            'date'             => $item->date,
            'debit'            => $item->type === 'debit'  ? $item->amount_usd : 0,
            'credit'           => $item->type === 'credit' ? $item->amount_usd : 0,
            'note'             => $item->note,
            'transaction_type' => 'credit_debit_note',
        ]);
    }

    private function getSales(Request $request, array $customerIds): \Illuminate\Support\Collection
    {
        $query = Sale::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'customer_id', 'created_at')
            ->whereIn('customer_id', $customerIds);

        $this->applyDateFilters($query, $request);

        return $query->get()->map(fn ($item) => [
            'id'               => $item->id,
            'code'             => $item->prefix . $item->code,
            'prefix'           => $item->prefix,
            'type'             => 'Sale Invoice',
            'date'             => $item->date,
            'debit'            => $item->total_usd,
            'credit'           => 0,
            'note'             => $item->note,
            'transaction_type' => 'sale',
        ]);
    }

    private function getPayments(Request $request, array $customerIds): \Illuminate\Support\Collection
    {
        $query = CustomerPayment::query()
            ->approved()
            ->select('id', 'code', 'prefix', 'date', 'amount_usd', 'note', 'customer_id', 'created_at')
            ->whereIn('customer_id', $customerIds);

        $this->applyDateFilters($query, $request);

        return $query->get()->map(fn ($item) => [
            'id'               => $item->id,
            'code'             => $item->prefix . $item->code,
            'prefix'           => $item->prefix,
            'type'             => 'Payment',
            'date'             => $item->date,
            'debit'            => 0,
            'credit'           => $item->amount_usd,
            'note'             => $item->note,
            'transaction_type' => 'payment',
        ]);
    }

    private function getReturns(Request $request, array $customerIds): \Illuminate\Support\Collection
    {
        $query = CustomerReturn::query()
            ->approved()
            ->received()
            ->select('id', 'code', 'prefix', 'date', 'total_usd', 'note', 'customer_id', 'created_at')
            ->whereIn('customer_id', $customerIds);

        $this->applyDateFilters($query, $request);

        return $query->get()->map(fn ($item) => [
            'id'               => $item->id,
            'code'             => $item->prefix . $item->code,
            'prefix'           => $item->prefix,
            'type'             => 'Sales Return',
            'date'             => $item->date,
            'debit'            => 0,
            'credit'           => $item->total_usd,
            'note'             => $item->note,
            'transaction_type' => 'return',
        ]);
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->has('from_date')) {
            $query->fromDate($request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->toDate($request->get('to_date'));
        }
    }

    private function calculateStats(\Illuminate\Support\Collection $transactions): array
    {
        $latest = $transactions->sortByDesc('date')->first();

        return [
            'total_debit'  => $transactions->sum('debit'),
            'total_credit' => $transactions->sum('credit'),
            'balance'      => $latest['balance'] ?? 0,
        ];
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
