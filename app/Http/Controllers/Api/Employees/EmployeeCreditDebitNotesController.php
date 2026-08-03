<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\EmployeeCreditDebitNoteStoreRequest;
use App\Http\Requests\Api\Employees\EmployeeCreditDebitNoteUpdateRequest;
use App\Http\Resources\Api\Employees\EmployeeCreditDebitNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeCreditDebitNotesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request)->with([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Employee credit/debit notes retrieved successfully',
            $notes,
            EmployeeCreditDebitNoteResource::class
        );
    }

    public function store(EmployeeCreditDebitNoteStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $note = DB::transaction(function () use ($data) {
                $note = EmployeeCreditDebitNote::create($data);

                $note->load([
                    'employee:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);

                return $note;
            });

            $message = 'Employee ' . $note->type . ' note created successfully';

            return ApiResponse::store($message, new EmployeeCreditDebitNoteResource($note));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create employee credit/debit note: ' . $e->getMessage());
        }
    }

    public function show(EmployeeCreditDebitNote $employeeCreditDebitNote): JsonResponse
    {
        $employeeCreditDebitNote->load([
            'employee:id,name,code,address,mobile',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Employee credit/debit note retrieved successfully',
            new EmployeeCreditDebitNoteResource($employeeCreditDebitNote)
        );
    }

    public function update(EmployeeCreditDebitNoteUpdateRequest $request, EmployeeCreditDebitNote $employeeCreditDebitNote): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $employeeCreditDebitNote) {
                $employeeCreditDebitNote->update($data);

                $employeeCreditDebitNote->load([
                    'employee:id,name,code',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'createdBy:id,name',
                    'updatedBy:id,name'
                ]);
            });

            $message = 'Employee ' . $employeeCreditDebitNote->type . ' note updated successfully';

            return ApiResponse::update($message, new EmployeeCreditDebitNoteResource($employeeCreditDebitNote));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update employee credit/debit note: ' . $e->getMessage());
        }
    }

    public function destroy(EmployeeCreditDebitNote $employeeCreditDebitNote): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can delete credit/debit notes', 403);
        }

        try {
            DB::transaction(function () use ($employeeCreditDebitNote) {
                $employeeCreditDebitNote->delete();
            });

            return ApiResponse::delete('Employee credit/debit note deleted successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete employee credit/debit note: ' . $e->getMessage());
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = EmployeeCreditDebitNote::onlyTrashed()
            ->with([
                'employee:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        $notes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed employee credit/debit notes retrieved successfully',
            $notes,
            EmployeeCreditDebitNoteResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can restore credit/debit notes', 403);
        }

        $note = EmployeeCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->restore();

        $note->load([
            'employee:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Employee credit/debit note restored successfully',
            new EmployeeCreditDebitNoteResource($note)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only administrators can permanently delete credit/debit notes', 403);
        }

        $note = EmployeeCreditDebitNote::onlyTrashed()->findOrFail($id);

        $note->forceDelete();

        return ApiResponse::delete('Employee credit/debit note permanently deleted successfully');
    }

    public function balance(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $totalCredit = (clone $query)->credit()->sum('amount_usd');
        $totalDebit  = (clone $query)->debit()->sum('amount_usd');

        return ApiResponse::show('Employee balance retrieved successfully', [
            'total_credit_usd' => (float) $totalCredit,
            'total_debit_usd'  => (float) $totalDebit,
            'balance_usd'      => (float) ($totalCredit - $totalDebit),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $stats = [
            'total_credit_amount_usd' => (clone $query)->credit()->sum('amount_usd'),
            'total_debit_amount_usd'  => (clone $query)->debit()->sum('amount_usd'),
        ];

        return ApiResponse::show('Employee credit/debit note statistics retrieved successfully', $stats);
    }

    private function query(Request $request)
    {
        $query = EmployeeCreditDebitNote::query()
            ->searchable($request)
            ->sortable($request);

        if ($request->has('employee_id')) {
            $query->byEmployee($request->employee_id);
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
            $query->toDate($request->to_date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        return $query;
    }
}
