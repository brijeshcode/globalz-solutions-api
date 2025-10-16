<?php

namespace App\Imports\Setup;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CustomerSetupImport implements WithMultipleSheets
{
    protected $skipDuplicates;
    protected $updateExisting;
    protected $results = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
        'sheets' => []
    ];

    protected $sheetImports = [];

    public function __construct($skipDuplicates = false, $updateExisting = false)
    {
        $this->skipDuplicates = $skipDuplicates;
        $this->updateExisting = $updateExisting;
    }

    public function sheets(): array
    {
        // Create import instances with options
        $customerTypeImport = new CustomerTypeImport($this->skipDuplicates, $this->updateExisting);
        $customerGroupImport = new CustomerGroupImport($this->skipDuplicates, $this->updateExisting);

        // Store references to retrieve results later
        $this->sheetImports['customer_types'] = $customerTypeImport;
        $this->sheetImports['customer_groups'] = $customerGroupImport;

        return [
            'Customer Types' => $customerTypeImport,
            'Customer Group' => $customerGroupImport,
            // Add more sheets as needed:
            // 'Supplier Types' => new SupplierTypeImport($this->skipDuplicates, $this->updateExisting),
            // 'Countries' => new CountryImport($this->skipDuplicates, $this->updateExisting),
            // 'Taxes' => new TaxImport($this->skipDuplicates, $this->updateExisting),
            // etc.
        ];
    }

    /**
     * Get aggregated import results from all sheets
     */
    public function getResults(): array
    {
        // Collect results from each sheet import
        foreach ($this->sheetImports as $key => $import) {
            $sheetResults = $import->getResults();
            $this->results['sheets'][$key] = $sheetResults;

            // Aggregate totals
            $this->results['total'] += $sheetResults['total'];
            $this->results['imported'] += $sheetResults['imported'];
            $this->results['updated'] += $sheetResults['updated'];
            $this->results['skipped'] += $sheetResults['skipped'];

            // Merge errors from all sheets
            if (!empty($sheetResults['errors'])) {
                foreach ($sheetResults['errors'] as $error) {
                    $this->results['errors'][] = array_merge($error, ['sheet' => $key]);
                }
            }
        }

        return $this->results;
    }
}
