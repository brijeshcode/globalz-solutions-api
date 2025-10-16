<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Imports\Setup\CustomerSetupImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportCustomerSetupController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
            'skip_duplicates' => 'sometimes|boolean',
            'update_existing' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $skipDuplicates = $request->boolean('skip_duplicates', false);
            $updateExisting = $request->boolean('update_existing', true);

            // Create import instance with options
            $import = new CustomerSetupImport($skipDuplicates, $updateExisting);

            // Process the import
            Excel::import($import, $file);

            // Get results from all sheets
            $results = $import->getResults();

            DB::commit();

            // Determine response status
            if ($results['imported'] === 0 && $results['updated'] === 0) {
                return ApiResponse::customError(
                    'No records were imported. Please check the file format and data.',
                    422,
                    $results
                );
            }

            return ApiResponse::store(
                'Customer setup data imported successfully!',
                $results
            );

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            DB::rollBack();

            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            return ApiResponse::error(
                'Import validation failed',
                422,
                ['errors' => $errors]
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Import failed: ' . $e->getMessage(),
                500,
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
        }
    }

    public function downloadTemplate()
    {
        $filePath = storage_path('app/templates/settings-template.xlsx');

        if (!file_exists($filePath)) {
            return ApiResponse::error('Template file not found', 404);
        }

        return response()->download($filePath, 'settings-template.xlsx');
    }
}
