<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ItemCurrentPricesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    private int $rowIndex = 0;

    public function __construct(private readonly \Illuminate\Support\Collection $rows) {}

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Item Code',
            'Item Name',
            'Unit',
            'Date',
            'Source Code',
            'On Invoice',
            'Discount %',
            'Net On Invoice',
            'Rate',
            'In USD',
            'Add. Cost %',
            'Add. Cost',
            'Final Cost',
            'Remark',
        ];
    }

    public function map($row): array
    {
        $this->rowIndex++;

        $latestHistory = $row['history'][0] ?? null;

        $onInvoice     = $latestHistory ? (float) ($latestHistory['cost_price'] ?? 0) : 0;
        $discount      = $latestHistory ? (float) ($latestHistory['discount_percent'] ?? 0) : 0;
        $netOnInvoice  = ($onInvoice !== null && $discount !== null)
            ? round($onInvoice * (1 - $discount / 100), 4)
            : null;

        $currency       = $latestHistory['currency'] ?? null;
        $symbol         = $currency['symbol'] ?? null;
        $symbolPosition = $currency['symbol_position'] ?? 'before';

        $formatWithSymbol = function (float $amount) use ($symbol, $symbolPosition): string {
            if (!$symbol) {
                return (string) $amount;
            }
            return $symbolPosition === 'after'
                ? $amount . ' ' . $symbol
                : $symbol . $amount;
        };

        return [
            $this->rowIndex,
            $row['item_code'],
            $row['item_name'],
            $row['unit']['short_name'] ?? null,
            $latestHistory ? $latestHistory['effective_date'] : null,
            $latestHistory ? $latestHistory['source_prefix'] . $latestHistory['source_code'] : null,
            $latestHistory ? $formatWithSymbol($onInvoice) : null,
            $discount,
            $latestHistory ? $formatWithSymbol($netOnInvoice) : null,
            $latestHistory ? (float) ($latestHistory['currency_rate'] ?? 1) : null,
            $latestHistory ? (float) ($latestHistory['price_usd'] ?? 0) : null,
            $latestHistory ? (float) ($latestHistory['exp_pct'] ?? 0) : null,
            $latestHistory ? (float) ($latestHistory['exp_share'] ?? 0) : null,
            (float) $row['price_usd'],
            $latestHistory ? $latestHistory['remark'] : null,
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
