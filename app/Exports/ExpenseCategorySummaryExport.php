<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class ExpenseCategorySummaryExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    private array  $rows           = [];
    private array  $rowMeta        = []; // 'top-level' | 'own' | 'child'
    private float  $grandTotal     = 0;
    private float  $grandPaid      = 0;
    private float  $grandDue       = 0;
    private int    $grandCount     = 0;

    // chart helper data: [[name, amount], ...]
    private array $chartData = [];

    public function __construct(private \Illuminate\Support\Collection $summary)
    {
        $this->buildRows();
    }

    private function buildRows(): void
    {
        foreach ($this->summary as $node) {
            // Top-level row
            $this->rows[]    = $this->nodeRow($node['name'], $node);
            $this->rowMeta[] = 'top-level';

            $this->chartData[] = [$node['name'], (float) $node['total_amount_usd']];

            $this->grandCount += (int) $node['total_transactions_count'];
            $this->grandTotal += (float) $node['total_amount_usd'];
            $this->grandPaid  += (float) $node['total_paid_amount_usd'];
            $this->grandDue   += (float) $node['total_due_amount_usd'];

            if (!empty($node['children'])) {
                // Parent's own-value row (only when it has own transactions)
                if ($node['own_transactions_count'] > 0) {
                    $this->rows[]    = $this->nodeRow('↳ Parent Value', $node, 'own');
                    $this->rowMeta[] = 'own';
                }

                foreach ($node['children'] as $child) {
                    $this->rows[]    = $this->nodeRow('   ↳ ' . $child['name'], $child);
                    $this->rowMeta[] = 'child';
                }
            }
        }

        // Grand total row
        $this->rows[]    = [
            'Grand Total',
            $this->grandCount,
            round($this->grandTotal, 2),
            round($this->grandPaid, 2),
            round($this->grandDue, 2),
        ];
        $this->rowMeta[] = 'total';
    }

    private function nodeRow(string $label, array $node, string $type = 'default'): array
    {
        if ($type === 'own') {
            return [
                $label,
                $node['own_transactions_count'],
                (float) $node['own_amount_usd'],
                (float) $node['own_paid_amount_usd'],
                (float) $node['own_due_amount_usd'],
            ];
        }

        return [
            $label,
            $node['total_transactions_count'],
            (float) $node['total_amount_usd'],
            (float) $node['total_paid_amount_usd'],
            (float) $node['total_due_amount_usd'],
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Category', 'Trans. #', 'Total (USD)', 'Paid (USD)', 'Due (USD)'];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            // Header row
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F81BD']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        // Style each data row
        foreach ($this->rowMeta as $i => $type) {
            $row = $i + 2; // +1 for header, +1 for 1-based
            switch ($type) {
                case 'top-level':
                    $styles[$row] = ['font' => ['bold' => true]];
                    break;
                case 'own':
                case 'child':
                    $styles[$row] = [
                        'font' => ['color' => ['argb' => 'FF555555']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF5F5F5']],
                    ];
                    break;
                case 'total':
                    $styles[$row] = [
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E75B6']],
                    ];
                    break;
            }
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $sheetTitle = $sheet->getTitle();

                if (empty($this->chartData)) {
                    return;
                }

                // Write helper data for the pie chart at column G (hidden from user)
                $sheet->setCellValue('G1', 'Category');
                $sheet->setCellValue('H1', 'Amount');

                $helperRow = 2;
                foreach ($this->chartData as [$name, $amount]) {
                    $sheet->setCellValue('G' . $helperRow, $name);
                    $sheet->setCellValue('H' . $helperRow, $amount);
                    $helperRow++;
                }

                $lastHelper = $helperRow - 1;
                $count      = count($this->chartData);

                // Hide helper columns
                $sheet->getColumnDimension('G')->setVisible(false);
                $sheet->getColumnDimension('H')->setVisible(false);

                // Build pie chart
                $labels = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    "'{$sheetTitle}'!\$G\$2:\$G\${$lastHelper}",
                    null,
                    $count
                );

                $values = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    "'{$sheetTitle}'!\$H\$2:\$H\${$lastHelper}",
                    null,
                    $count
                );

                $series = new DataSeries(
                    DataSeries::TYPE_PIECHART,
                    null,
                    range(0, 0),
                    [],
                    [$labels],
                    [$values]
                );

                $plotArea = new PlotArea(null, [$series]);
                $legend   = new Legend(Legend::POSITION_RIGHT, null, false);
                $title    = new Title('Expense Category Summary');

                $chart = new Chart('expense_summary_chart', $title, $legend, $plotArea);

                // Place chart below the data table
                $chartStartRow = count($this->rows) + 3;
                $chart->setTopLeftPosition('A' . $chartStartRow);
                $chart->setBottomRightPosition('E' . ($chartStartRow + 20));

                $sheet->addChart($chart);
            },
        ];
    }
}
