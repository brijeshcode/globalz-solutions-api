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
            'priceListINV:id,code',
            'priceListINX:id,code',
        ]);
    }

    public function headings(): array
    {
        return [
            'code',
            '*name',
            '*customer_type',
            'customer_group',
            'customer_province',
            'customer_zone',
            'starting_balance',
            'address',
            '*city',
            'telephone',
            '*mobile',
            'email',
            'website',
            'google_map_link',
            'contact_name',
            'gps_coordinates',
            'mof_tax_number',
            '*salesperson',
            'payment_term',
            'discount_percentage',
            'credit_limit',
            '*price_list_inv_code',
            '*price_list_inx_code',
            'notes',
            'created_at',
            'total_old_sales',
            'is_active',
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
            (float) ($customer->current_balance ?? 0),
            $customer->address,
            $customer->city,
            $customer->telephone,
            $customer->mobile,
            $customer->email,
            $customer->url,
            $customer->google_map,
            $customer->contact_name,
            $customer->gps_coordinates,
            $customer->mof_tax_number,
            $customer->salesperson?->name,
            $customer->customerPaymentTerm?->name,
            $customer->discount_percentage ? (float) $customer->discount_percentage : null,
            $customer->credit_limit ? (float) $customer->credit_limit : null,
            $customer->priceListINV?->code,
            $customer->priceListINX?->code,
            $customer->notes,
            $customer->created_at?->format('Y-m-d'),
            $customer->total_old_sales ? (float) $customer->total_old_sales : null,
            $customer->is_active ? 1 : 0,
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
