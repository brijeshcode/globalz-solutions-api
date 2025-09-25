<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerCreditDebitNotesStoreRequest;
use App\Http\Requests\Api\Customers\CustomerCreditDebitNotesUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerCreditDebitNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerCreditDebitNotesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerCreditDebitNote::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
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

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer credit/debit notes retrieved successfully',
            $notes,
            CustomerCreditDebitNoteResource::class
        );
    }

    public function store(CustomerCreditDebitNotesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $note = CustomerCreditDebitNote::create($data);

        $note->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer ' . $note->type . ' note created successfully';

        return ApiResponse::store($message, new CustomerCreditDebitNoteResource($note));
    }

    public function show(CustomerCreditDebitNote $customerCreditDebitNote): JsonResponse
    {
        $customerCreditDebitNote->load([
            'customer:id,name,code,address,city,mobile',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer credit/debit note retrieved successfully',
            new CustomerCreditDebitNoteResource($customerCreditDebitNote)
        );
    }

    public function update(CustomerCreditDebitNotesUpdateRequest $request, CustomerCreditDebitNote $customerCreditDebitNote): JsonResponse
    {
        $data = $request->validated();

        $customerCreditDebitNote->update($data);

        $customerCreditDebitNote->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer ' . $customerCreditDebitNote->type . ' note updated successfully';

        return ApiResponse::update($message, new CustomerCreditDebitNoteResource($customerCreditDebitNote));
    }

    public function destroy(CustomerCreditDebitNote $customerCreditDebitNote): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can delete credit/debit notes', 403);
        }

        $customerCreditDebitNote->delete();

        return ApiResponse::delete('Customer credit/debit note deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerCreditDebitNote::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer credit/debit notes retrieved successfully',
            $notes,
            CustomerCreditDebitNoteResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can restore credit/debit notes', 403);
        }

        $note = CustomerCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->restore();

        $note->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer credit/debit note restored successfully',
            new CustomerCreditDebitNoteResource($note)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can permanently delete credit/debit notes', 403);
        }

        $note = CustomerCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->forceDelete();

        return ApiResponse::delete('Customer credit/debit note permanently deleted successfully');
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total_notes' => CustomerCreditDebitNote::count(),
            'credit_notes' => CustomerCreditDebitNote::credit()->count(),
            'debit_notes' => CustomerCreditDebitNote::debit()->count(),
            'trashed_notes' => CustomerCreditDebitNote::onlyTrashed()->count(),
            'total_credit_amount' => CustomerCreditDebitNote::credit()->sum('amount'),
            'total_debit_amount' => CustomerCreditDebitNote::debit()->sum('amount'),
            'total_credit_amount_usd' => CustomerCreditDebitNote::credit()->sum('amount_usd'),
            'total_debit_amount_usd' => CustomerCreditDebitNote::debit()->sum('amount_usd'),
            'notes_by_prefix' => CustomerCreditDebitNote::selectRaw('prefix, count(*) as count, sum(amount) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'notes_by_currency' => CustomerCreditDebitNote::with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(amount) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
            'recent_notes' => CustomerCreditDebitNote::with(['customer:id,name,code', 'createdBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return ApiResponse::show('Customer credit/debit note statistics retrieved successfully', $stats);
    }
}
