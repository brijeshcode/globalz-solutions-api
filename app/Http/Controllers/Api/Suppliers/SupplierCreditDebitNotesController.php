<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Suppliers\SupplierCreditDebitNotesStoreRequest;
use App\Http\Requests\Api\Suppliers\SupplierCreditDebitNotesUpdateRequest;
use App\Http\Resources\Api\Suppliers\SupplierCreditDebitNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierCreditDebitNotesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Supplier credit/debit notes retrieved successfully',
            $notes,
            SupplierCreditDebitNoteResource::class
        );
    }

    public function store(SupplierCreditDebitNotesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $note = DB::transaction(function () use ($data) {
                $note = SupplierCreditDebitNote::create($data);

                $note->load([
                    'supplier:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);

                return $note;
            });

            $message = 'Supplier ' . $note->type . ' note created successfully';

            return ApiResponse::store($message, new SupplierCreditDebitNoteResource($note));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create supplier credit/debit note: ' . $e->getMessage());
        }
    }

    public function show(SupplierCreditDebitNote $supplierCreditDebitNote): JsonResponse
    {
        $supplierCreditDebitNote->load([
            'supplier:id,name,code,address,mobile',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Supplier credit/debit note retrieved successfully',
            new SupplierCreditDebitNoteResource($supplierCreditDebitNote)
        );
    }

    public function update(SupplierCreditDebitNotesUpdateRequest $request, SupplierCreditDebitNote $supplierCreditDebitNote): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $supplierCreditDebitNote) {
                $supplierCreditDebitNote->update($data);

                $supplierCreditDebitNote->load([
                    'supplier:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);
            });

            $message = 'Supplier ' . $supplierCreditDebitNote->type . ' note updated successfully';

            return ApiResponse::update($message, new SupplierCreditDebitNoteResource($supplierCreditDebitNote));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update supplier credit/debit note: ' . $e->getMessage());
        }
    }

    public function destroy(SupplierCreditDebitNote $supplierCreditDebitNote): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can delete credit/debit notes', 403);
        }

        try {
            DB::transaction(function () use ($supplierCreditDebitNote) {
                $supplierCreditDebitNote->delete();
            });

            return ApiResponse::delete('Supplier credit/debit note deleted successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete supplier credit/debit note: ' . $e->getMessage());
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = SupplierCreditDebitNote::onlyTrashed()
            ->with([
                'supplier:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed supplier credit/debit notes retrieved successfully',
            $notes,
            SupplierCreditDebitNoteResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can restore credit/debit notes', 403);
        }

        $note = SupplierCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->restore();

        $note->load([
            'supplier:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Supplier credit/debit note restored successfully',
            new SupplierCreditDebitNoteResource($note)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can permanently delete credit/debit notes', 403);
        }

        $note = SupplierCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->forceDelete();

        return ApiResponse::delete('Supplier credit/debit note permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $stats = [
            'total_notes' => (clone $query)->count(),
            'credit_notes' => (clone $query)->credit()->count(),
            'debit_notes' => (clone $query)->debit()->count(),
            'trashed_notes' => (clone $query)->onlyTrashed()->count(),
            'total_credit_amount' => (clone $query)->credit()->sum('amount'),
            'total_debit_amount' => (clone $query)->debit()->sum('amount'),
            'total_credit_amount_usd' => (clone $query)->credit()->sum('amount_usd'),
            'total_debit_amount_usd' => (clone $query)->debit()->sum('amount_usd'),
        ];

        return ApiResponse::show('Supplier credit/debit note statistics retrieved successfully', $stats);
    }

    private function query(Request $request)
    {
        $query = SupplierCreditDebitNote::query()
            ->with([
                'supplier:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date);
        } 
        
        if ($request->has('to_date')) {
            $query->toDate( $request->to_date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        return $query;
    }
}
