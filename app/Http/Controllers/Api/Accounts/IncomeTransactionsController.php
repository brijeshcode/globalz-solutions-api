<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\IncomeTransactionsStoreRequest;
use App\Http\Requests\Api\Accounts\IncomeTransactionsUpdateRequest;
use App\Http\Resources\Api\Accounts\IncomeTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Accounts\IncomeTransaction;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeTransactionsController extends Controller
{
    use HasPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->updateExistingTransactionsCurrency();

        $query = IncomeTransaction::query()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'incomeCategory:id,name',
                'account:id,name',
                'currency:id,name,code,symbol'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('income_category_id')) {
            $query->where('income_category_id', $request->input('income_category_id'));
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->input('start_date'), $request->input('end_date'));
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        $incomeTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Income transactions retrieved successfully',
            $incomeTransactions,
            IncomeTransactionResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IncomeTransactionsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Fetch account to get currency details
        $account = Account::with('currency.activeRate')->findOrFail($data['account_id']);

        // Set currency_id from account
        $data['currency_id'] = $account->currency_id;

        // Get currency rate (use active rate or default to 1)
        $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;

        // Convert amount to USD
        $data['amount_usd'] = CurrencyHelper::toUsd(
            $data['currency_id'],
            $data['amount'],
            $data['currency_rate']
        );

        $incomeTransaction = IncomeTransaction::create($data);
        
        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $incomeTransaction->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            // Upload documents
            $incomeTransaction->createDocuments($files, [
                'type' => 'income_transaction_document'
            ]);
        }

        $incomeTransaction->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'incomeCategory:id,name',
            'account:id,name',
            'currency:id,name,code,symbol',
            'documents'
        ]);

        return ApiResponse::store(
            'Income transaction created successfully',
            new IncomeTransactionResource($incomeTransaction)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(IncomeTransaction $incomeTransaction): JsonResponse
    {
        $incomeTransaction->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'incomeCategory:id,name',
            'account:id,name',
            'currency:id,name,code,symbol',
            'documents'
        ]);

        return ApiResponse::show(
            'Income transaction retrieved successfully',
            new IncomeTransactionResource($incomeTransaction)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(IncomeTransactionsUpdateRequest $request, IncomeTransaction $incomeTransaction): JsonResponse
    {
        $data = $request->validated();
        // Remove code from data if present (code is system generated only, not updatable)
        unset($data['code']);

        // If account_id or amount changed, recalculate currency fields
        if (isset($data['account_id']) && $data['account_id'] != $incomeTransaction->account_id) {
            // Account changed - fetch new account's currency
            $account = Account::with('currency.activeRate')->findOrFail($data['account_id']);
            $data['currency_id'] = $account->currency_id;
            $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;

            // Convert amount to USD
            $amount = $data['amount'] ?? $incomeTransaction->amount;
            $data['amount_usd'] = CurrencyHelper::toUsd(
                $data['currency_id'],
                $amount,
                $data['currency_rate']
            );
        } elseif (isset($data['amount']) && $data['amount'] != $incomeTransaction->amount) {
            // Amount changed but same account - use existing currency
            $data['amount_usd'] = CurrencyHelper::toUsd(
                $incomeTransaction->currency_id,
                $data['amount'],
                $incomeTransaction->currency_rate
            );
        }

        $incomeTransaction->update($data);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $incomeTransaction->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            $incomeTransaction->updateDocuments($files, [
                'type' => 'income_transaction_document'
            ]);
        }

        $incomeTransaction->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'incomeCategory:id,name',
            'account:id,name',
            'currency:id,name,code,symbol',
            'documents'
        ]);

        return ApiResponse::update(
            'Income transaction updated successfully',
            new IncomeTransactionResource($incomeTransaction)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IncomeTransaction $incomeTransaction): JsonResponse
    {
        $incomeTransaction->delete();

        return ApiResponse::delete('Income transaction deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = IncomeTransaction::onlyTrashed()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'incomeCategory:id,name',
                'account:id,name',
                'currency:id,name,code,symbol'
            ])
            ->searchable($request)
            ->sortable($request);

        $incomeTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed income transactions retrieved successfully',
            $incomeTransactions,
            IncomeTransactionResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $incomeTransaction = IncomeTransaction::onlyTrashed()->findOrFail($id);
        $incomeTransaction->restore();

        return ApiResponse::update('Income transaction restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $incomeTransaction = IncomeTransaction::onlyTrashed()->findOrFail($id);
        $incomeTransaction->forceDelete();

        return ApiResponse::delete('Income transaction permanently deleted successfully');
    }

    /**
     * Get the next suggested income transaction code
     */
    public function getNextCode(): JsonResponse
    {
        $nextCode = IncomeTransaction::getNextSuggestedCode();
        
        return ApiResponse::show('Next income transaction code retrieved successfully', [
            'code' => $nextCode,
            'is_available' => true,
            'message' => 'Next available code'
        ]);
    }

    /**
     * Upload documents for an income transaction
     */
    public function uploadDocuments(Request $request, IncomeTransaction $incomeTransaction): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|max:15',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $files = $request->file('documents');
        
        // Validate each document file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $incomeTransaction->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }
        
        // Upload documents
        $uploadedDocuments = $incomeTransaction->createDocuments($files, [
            'type' => 'income_transaction_document'
        ]);

        return ApiResponse::store(
            'Documents uploaded successfully',
            [
                'uploaded_count' => $uploadedDocuments->count(),
                'documents' => $uploadedDocuments->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'file_name' => $doc->file_name,
                        'thumbnail_url' => $doc->thumbnail_url,
                        'download_url' => $doc->download_url,
                    ];
                })
            ]
        );
    }

    /**
     * Delete specific documents for an income transaction
     */
    public function deleteDocuments(Request $request, IncomeTransaction $incomeTransaction): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this income transaction
        $documentCount = $incomeTransaction->documents()
            ->whereIn('id', $request->document_ids)
            ->count();

        if ($documentCount !== count($request->document_ids)) {
            return ApiResponse::customError('Some documents do not belong to this income transaction', 403);
        }

        // Delete the documents
        $deleted = $incomeTransaction->deleteDocuments($request->document_ids);

        return ApiResponse::delete(
            'Documents deleted successfully',
            ['deleted_count' => $deleted ? count($request->document_ids) : 0]
        );
    }

    /**
     * Get documents for a specific income transaction
     */
    public function getDocuments(IncomeTransaction $incomeTransaction): JsonResponse
    {
        $documents = $incomeTransaction->documents()->get();

        return ApiResponse::show(
            'Income transaction documents retrieved successfully',
            $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'original_name' => $doc->original_name,
                    'file_name' => $doc->file_name,
                    'file_size' => $doc->file_size,
                    'file_size_human' => $doc->file_size_human,
                    'thumbnail_url' => $doc->thumbnail_url,
                    'download_url' => $doc->download_url,
                    'uploaded_at' => $doc->created_at,
                ];
            })
        );
    }

    /**
     * Update existing income transactions with currency fields
     */
    public function updateExistingTransactionsCurrency(): JsonResponse
    {
        $updated = 0;
        $failed = 0;
        $errors = [];

        // Get all income transactions where currency_id is null
        $transactions = IncomeTransaction::whereNull('currency_id')
            ->with('account.currency.activeRate')
            ->get();

        foreach ($transactions as $transaction) {
            try {
                $account = $transaction->account;

                if (!$account || !$account->currency_id) {
                    $failed++;
                    $errors[] = "Transaction INC{$transaction->code}: Account has no currency";
                    continue;
                }

                // Set currency_id from account
                $currencyId = $account->currency_id;

                // Get currency rate (use active rate or default to 1)
                $currencyRate = $account->currency->activeRate->rate ?? 1;

                // Convert amount to USD
                $amountUsd = CurrencyHelper::toUsd(
                    $currencyId,
                    $transaction->amount,
                    $currencyRate
                );

                // Update transaction
                $transaction->updateQuietly([
                    'currency_id' => $currencyId,
                    'currency_rate' => $currencyRate,
                    'amount_usd' => $amountUsd,
                ]);

                $updated++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Transaction INC{$transaction->code}: " . $e->getMessage();
            }
        }

        $result = [
            'total' => $transactions->count(),
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];

        if ($failed > 0) {
            return ApiResponse::customError('Some transactions failed to update', 422, $result);
        }

        return ApiResponse::show('Income transactions updated successfully', $result);
    }
}
