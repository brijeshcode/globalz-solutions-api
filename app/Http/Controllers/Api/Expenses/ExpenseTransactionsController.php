<?php

namespace App\Http\Controllers\Api\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\ExpenseTransactionsStoreRequest;
use App\Http\Requests\Api\Expenses\ExpenseTransactionsUpdateRequest;
use App\Http\Resources\Api\Expenses\ExpenseTransactionResource;
use App\Models\Expenses\ExpenseTransaction;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseTransactionsController extends Controller
{
    use HasPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseTransaction::query()
            ->with([
                'createdBy:id,name', 
                'updatedBy:id,name', 
                'expenseCategory:id,name', 
                'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->input('expense_category_id'));
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

        $expenseTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Expense transactions retrieved successfully',
            $expenseTransactions,
            ExpenseTransactionResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseTransactionsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $expenseTransaction = ExpenseTransaction::create($data);
        
        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $expenseTransaction->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            // Upload documents
            $expenseTransaction->createDocuments($files, [
                'type' => 'expense_transaction_document'
            ]);
        }

        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name',
            'documents'
        ]);

        return ApiResponse::store(
            'Expense transaction created successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name',
            'documents'
        ]);

        return ApiResponse::show(
            'Expense transaction retrieved successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseTransactionsUpdateRequest $request, ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->update($request->validated());

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $expenseTransaction->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            $expenseTransaction->updateDocuments($files, [
                'type' => 'expense_transaction_document'
            ]);
        }

        $expenseTransaction->load([
            'createdBy:id,name', 
            'updatedBy:id,name', 
            'expenseCategory:id,name', 
            'account:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Expense transaction updated successfully',
            new ExpenseTransactionResource($expenseTransaction)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $expenseTransaction->delete();

        return ApiResponse::delete('Expense transaction deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = ExpenseTransaction::onlyTrashed()
            ->with([
                'createdBy:id,name', 
                'updatedBy:id,name', 
                'expenseCategory:id,name', 
                'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        $expenseTransactions = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed expense transactions retrieved successfully',
            $expenseTransactions,
            ExpenseTransactionResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $expenseTransaction = ExpenseTransaction::onlyTrashed()->findOrFail($id);
        $expenseTransaction->restore();

        return ApiResponse::update('Expense transaction restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $expenseTransaction = ExpenseTransaction::onlyTrashed()->findOrFail($id);
        $expenseTransaction->forceDelete();

        return ApiResponse::delete('Expense transaction permanently deleted successfully');
    }

    /**
     * Get the next suggested expense transaction code
     */
    public function getNextCode(): JsonResponse
    {
        $nextCode = ExpenseTransaction::getNextSuggestedCode();
        
        return ApiResponse::show('Next expense transaction code retrieved successfully', [
            'code' => $nextCode,
            'is_available' => true,
            'message' => 'Next available code'
        ]);
    }

    /**
     * Upload documents for an expense transaction
     */
    public function uploadDocuments(Request $request, ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|max:15',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $files = $request->file('documents');
        
        // Validate each document file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $expenseTransaction->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }
        
        // Upload documents
        $uploadedDocuments = $expenseTransaction->createDocuments($files, [
            'type' => 'expense_transaction_document'
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
     * Delete specific documents for an expense transaction
     */
    public function deleteDocuments(Request $request, ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this expense transaction
        $documentCount = $expenseTransaction->documents()
            ->whereIn('id', $request->document_ids)
            ->count();

        if ($documentCount !== count($request->document_ids)) {
            return ApiResponse::customError('Some documents do not belong to this expense transaction', 403);
        }

        // Delete the documents
        $deleted = $expenseTransaction->deleteDocuments($request->document_ids);

        return ApiResponse::delete(
            'Documents deleted successfully',
            ['deleted_count' => $deleted ? count($request->document_ids) : 0]
        );
    }

    /**
     * Get documents for a specific expense transaction
     */
    public function getDocuments(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $documents = $expenseTransaction->documents()->get();

        return ApiResponse::show(
            'Expense transaction documents retrieved successfully',
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
}
