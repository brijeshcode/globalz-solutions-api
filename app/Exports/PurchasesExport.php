<?php

namespace App\Exports;

use App\Models\Suppliers\Purchase;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PurchasesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(protected $query)
    {
    }

    public function query()
    {
        return $this->query->reorder()->orderBy('date', 'desc')->with([
            'supplier:id,name',
            'warehouse:id,name',
            'currency:id,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator',
            'purchaseExpenses:id,purchase_id,expense_transaction_id,exclude_from_item_cost',
            'purchaseExpenses.expenseTransaction:id,amount_usd,vat_amount_usd',
        ]);
    }

    public function headings(): array
    {
        return [
            'Date',
            'ID',
            'Supplier',
            'Currency',
            'Amount',
            'USD',
            'VAT',
            'Cost Dist.',
            '% of Total',
            'Excl Cost',
            'Warehouse',
            'Status',
        ];
    }

    public function map($purchase): array
    {
        // Split purchase expenses into cost-distributed (added to item cost) and
        // excluded, mirroring how the listing screen derives these columns.
        $costDist = 0.0;
        $exclCost = 0.0;

        foreach ($purchase->purchaseExpenses as $expense) {
            $tx = $expense->expenseTransaction;
            if (! $tx) {
                continue;
            }

            $amount = (float) $tx->amount_usd + (float) $tx->vat_amount_usd;

            if ($expense->exclude_from_item_cost) {
                $exclCost += $amount;
            } else {
                $costDist += $amount;
            }
        }

        $finalTotalUsd = (float) $purchase->total_usd;
        $percentOfTotal = $finalTotalUsd != 0.0
            ? round($costDist / $finalTotalUsd * 100, 2)
            : 0.0;

        return [
            $purchase->date?->format('Y-m-d'),
            $purchase->prefix . $purchase->code,
            $purchase->supplier?->name,
            $purchase->currency?->code,
            $purchase->currency
                ? $purchase->currency->formatAmount((float) $purchase->total)
                : number_format((float) $purchase->total, 2),
            (float) $purchase->total_usd,
            (float) $purchase->tax_usd,
            round($costDist, 2),
            $percentOfTotal,
            round($exclCost, 2),
            $purchase->warehouse?->name,
            $purchase->status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
            ],
        ];
    }
}
