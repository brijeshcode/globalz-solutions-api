<?php

namespace App\Imports\Setup;

use App\Models\Setups\Customers\CustomerType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class CustomerTypeImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    protected $skipDuplicates;
    protected $updateExisting;
    protected $results = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    public function __construct($skipDuplicates = false, $updateExisting = false)
    {
        $this->skipDuplicates = $skipDuplicates;
        $this->updateExisting = $updateExisting;
    }

    /**
     * Process each collection/chunk
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $this->results['total']++;
            $rowNumber = $index + 2; // +2 because of header row and 0-based index

            try {
                // Validate row data
                $result = $this->validateRow($row->toArray(), $rowNumber);

                if (isset($result['error'])) {
                    $this->results['skipped']++;
                    $this->results['errors'][] = [
                        'row' => $rowNumber,
                        'error' => $result['error']
                    ];
                    continue;
                }

                $validatedData = $result['data'];

                // Check for duplicates by name
                $existingType = CustomerType::withTrashed()
                    ->where('name', $validatedData['name'])
                    ->first();

                // Handle duplicate logic
                if ($existingType) {
                    if ($this->updateExisting) {
                        // Update existing customer type
                        $existingType->update($validatedData);
                        $this->results['updated']++;
                    } else {
                        // Skip duplicate
                        $this->results['skipped']++;
                        $this->results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => "Customer type already exists with name: {$validatedData['name']}"
                        ];
                    }
                } else {
                    // Create new customer type
                    CustomerType::create($validatedData);
                    $this->results['imported']++;
                }

            } catch (\Exception $e) {
                $this->results['skipped']++;
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage()
                ];
                Log::error("CustomerType Row {$rowNumber} processing failed", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }
    }

    /**
     * Validate and transform row data
     */
    protected function validateRow(array $row, int $rowNumber): array
    {
        // Map column headers to expected fields
        $data = [
            'name' => $row['name'] ?? null,
            'description' => $row['description'] ?? null,
            'is_active' => isset($row['is_active']) ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
        ];

        // Required field validation
        if (empty($data['name'])) {
            return ['error' => 'Customer type name is required'];
        }

        // Clean up null values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });

        return [
            'data' => $data
        ];
    }

    /**
     * Batch size for bulk inserts
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Chunk size for reading
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Get import results
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
