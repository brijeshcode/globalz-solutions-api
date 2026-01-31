<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
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
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;

class CustomerCreditDebitNotesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request)
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->sortable($request)
            ;

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

        try {
            $note = DB::transaction(function () use ($data) {
                $note = CustomerCreditDebitNote::create($data);

                $note->load([
                    'customer:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);

                return $note;
            });

            $message = 'Customer ' . $note->type . ' note created successfully';

            return ApiResponse::store($message, new CustomerCreditDebitNoteResource($note));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create customer credit/debit note: ' . $e->getMessage());
        }
    }

    public function show(CustomerCreditDebitNote $customerCreditDebitNote): JsonResponse
    {
        $customerCreditDebitNote->load([
            'customer:id,name,code,address,city,mobile',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
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

        try {
            DB::transaction(function () use ($data, $customerCreditDebitNote) {
                $customerCreditDebitNote->update($data);

                $customerCreditDebitNote->load([
                    'customer:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);
            });

            $message = 'Customer ' . $customerCreditDebitNote->type . ' note updated successfully';

            return ApiResponse::update($message, new CustomerCreditDebitNoteResource($customerCreditDebitNote));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update customer credit/debit note: ' . $e->getMessage());
        }
    }

    public function destroy(CustomerCreditDebitNote $customerCreditDebitNote): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can delete credit/debit notes', 403);
        }

        try {
            DB::transaction(function () use ($customerCreditDebitNote) {
                $customerCreditDebitNote->delete();
            });

            return ApiResponse::delete('Customer credit/debit note deleted successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete customer credit/debit note: ' . $e->getMessage());
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerCreditDebitNote::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
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
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
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

    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $stats = [
            // 'total_notes' => (clone $query)->count(),
            'total_credit_notes' => (clone $query)->credit()->count(),
            'total_debit_notes' => (clone $query)->debit()->count(),
            // 'trashed_notes' => (clone $query)->onlyTrashed()->count(),
            // 'total_credit_amount' => (clone $query)->credit()->sum('amount'),
            // 'total_debit_amount' => (clone $query)->debit()->sum('amount'),
            'total_credit_amount_usd' => (clone $query)->credit()->sum('amount_usd'),
            'total_debit_amount_usd' => (clone $query)->debit()->sum('amount_usd'),
        ];

        return ApiResponse::show('Customer credit/debit note statistics retrieved successfully', $stats);
    }

    private function query(Request $request)
    {
        $query = CustomerCreditDebitNote::query()
            ->searchable($request)
            ;

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

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by salesman if user has salesman role
        if (RoleHelper::isSalesman()) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $query->whereHas('customer', function ($q) use ($salesmanEmployee) {
                    $q->where('salesperson_id', $salesmanEmployee->id);
                });
            }
        }

        return $query;
    }
}
