<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomerPaymentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(protected $query)
    {
    }

    public function query()
    {
        return $this->query->reorder()->orderBy('date', 'desc')->with([
            'customer:id,name,code,salesperson_id',
            'customer.salesperson:id,name',
            'currency:id,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator',
            'account:id,name',
            'createdBy:id,name',
        ]);
    }

    public function headings(): array
    {
        return [
            'Date',
            'ID',
            'Customer',
            'Salesperson',
            'Currency',
            'Amount',
            'USD',
            'Account',
            'RTC Book #',
            'Note',
            'Created At',
            'Created By',
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->date?->format('Y-m-d'),
            $payment->prefix . $payment->code,
            $payment->customer?->name,
            $payment->customer?->salesperson?->name,
            $payment->currency?->code,
            $payment->currency
                ? $payment->currency->formatAmount((float) $payment->amount)
                : number_format((float) $payment->amount, 2),
            (float) $payment->amount_usd,
            $payment->account?->name,
            $payment->rtc_book_number,
            $payment->note,
            $payment->created_at?->format('Y-m-d H:i:s'),
            $payment->createdBy?->name,
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
