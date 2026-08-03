<?php

namespace App\Exports;

use App\Helpers\FeatureHelper;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExpenseTransactionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    private bool $isMultiCurrency;

    public function __construct(protected $query)
    {
        $this->isMultiCurrency = FeatureHelper::isMultiCurrency();
    }

    public function query()
    {
        return $this->query->reorder()->orderBy('date', 'desc')->with([
            'expenseCategory:id,name',
            'currency:id,code',
        ]);
    }

    public function headings(): array
    {
        if ($this->isMultiCurrency) {
            return ['Date', 'Code', 'Amount', 'USD', 'VAT', 'Paid', 'Due', 'Category', 'Subject', 'Note'];
        }

        return ['Date', 'Code', 'Amount', 'VAT', 'Paid', 'Due', 'Category', 'Subject', 'Note'];
    }

    public function map($expense): array
    {
        if ($this->isMultiCurrency) {
            return [
                $expense->date?->format('Y-m-d'),
                $expense->code,
                (float) $expense->amount,
                (float) $expense->amount_usd,
                (float) $expense->vat_amount,
                (float) $expense->paid_amount_usd,
                round((float) $expense->total_amount_usd - (float) $expense->paid_amount_usd, 2),
                $expense->expenseCategory?->name,
                $expense->subject,
                $expense->note,
            ];
        }

        return [
            $expense->date?->format('Y-m-d'),
            $expense->code,
            (float) $expense->amount,
            (float) $expense->vat_amount,
            (float) $expense->paid_amount,
            (float) $expense->due_amount,
            $expense->expenseCategory?->name,
            $expense->subject,
            $expense->note,
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
