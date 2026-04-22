<?php

namespace App\Exports;

use App\Models\Customers\Customer;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class CustomersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(protected $query) {}

    public function query()
    {
        return $this->query->reorder()->orderBy('name', 'asc')->with([
            'customerType:id,name',
            'customerGroup:id,name',
            'customerProvince:id,name',
            'customerZone:id,name',
            'salesperson:id,code,name',
            'customerPaymentTerm:id,name',
            'priceListINV:id,code,description',
            'priceListINX:id,code,description',
        ]);
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Type',
            'Group',
            'Province',
            'Zone',
            'Salesperson',
            'Payment Term',
            'Price List INV',
            'Price List INX',
            'Current Balance',
            'Credit Limit',
            'Discount %',
            'Address',
            'City',
            'Telephone',
            'Mobile',
            'Email',
            'Contact Name',
            'MOF Tax Number',
            'Notes',
            'Active',
            'Created At',
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->code,
            $customer->name,
            $customer->customerType?->name,
            $customer->customerGroup?->name,
            $customer->customerProvince?->name,
            $customer->customerZone?->name,
            $customer->salesperson ? $customer->salesperson->code . ' - ' . $customer->salesperson->name : null,
            $customer->customerPaymentTerm?->name,
            $customer->priceListINV?->code,
            $customer->priceListINX?->code,
            (float) ($customer->current_balance ?? 0),
            $customer->credit_limit ? (float) $customer->credit_limit : null,
            $customer->discount_percentage ? (float) $customer->discount_percentage : null,
            $customer->address,
            $customer->city,
            $customer->telephone,
            $customer->mobile,
            $customer->email,
            $customer->contact_name,
            $customer->mof_tax_number,
            $customer->notes,
            $customer->is_active ? 'Yes' : 'No',
            $customer->created_at?->format('Y-m-d'),
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
