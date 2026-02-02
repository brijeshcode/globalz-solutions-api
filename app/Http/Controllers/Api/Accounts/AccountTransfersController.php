<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\AccountTransfersStoreRequest;
use App\Http\Requests\Api\Accounts\AccountTransfersUpdateRequest;
use App\Http\Resources\Api\Accounts\AccountTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\AccountTransfer;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountTransfersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $transfers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Account transfers retrieved successfully',
            $transfers,
            AccountTransferResource::class
        );
    }

    public function store(AccountTransfersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $transfer = DB::transaction(function () use ($data) {
                $transfer = AccountTransfer::create($data);
                $transfer->load([
                    'fromAccount:id,name,account_type_id,currency_id,current_balance',
                    'toAccount:id,name,account_type_id,currency_id,current_balance',
                    'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
                    
                ]);

                return $transfer;
            });

            return ApiResponse::store('Account transfer created successfully', new AccountTransferResource($transfer));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create account transfer: ' . $e->getMessage());
        }
    }

    public function show(AccountTransfer $accountTransfer): JsonResponse
    {
        $accountTransfer->load([
            'fromAccount:id,name,account_type_id,currency_id,current_balance',
            'toAccount:id,name,account_type_id,currency_id,current_balance',
            'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
            
        ]);

        return ApiResponse::show(
            'Account transfer retrieved successfully',
            new AccountTransferResource($accountTransfer)
        );
    }

    public function update(AccountTransfersUpdateRequest $request, AccountTransfer $accountTransfer): JsonResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $accountTransfer) {
                $accountTransfer->update($data);

                $accountTransfer->load([
                    'fromAccount:id,name,account_type_id,currency_id,current_balance',
                    'toAccount:id,name,account_type_id,currency_id,current_balance',
                    'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
                    
                ]);
            });

            return ApiResponse::update('Account transfer updated successfully', new AccountTransferResource($accountTransfer));

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update account transfer: ' . $e->getMessage());
        }
    }

    public function destroy(AccountTransfer $accountTransfer): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only administrators can delete account transfers', 403);
        }

        try {
            DB::transaction(function () use ($accountTransfer) {
                $accountTransfer->delete();
            });

            return ApiResponse::delete('Account transfer deleted successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete account transfer: ' . $e->getMessage());
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = AccountTransfer::onlyTrashed()
            ->with([
                'fromAccount:id,name,account_type_id,currency_id',
                'toAccount:id,name,account_type_id,currency_id',
                'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
                
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('from_account_id')) {
            $query->where('from_account_id', $request->from_account_id);
        }

        if ($request->has('to_account_id')) {
            $query->where('to_account_id', $request->to_account_id);
        }

        if ($request->has('from_currency_id')) {
            $query->where('from_currency_id', $request->from_currency_id);
        }

        if ($request->has('to_currency_id')) {
            $query->where('to_currency_id', $request->to_currency_id);
        }

        $transfers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed account transfers retrieved successfully',
            $transfers,
            AccountTransferResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only administrators can restore account transfers', 403);
        }

        $transfer = AccountTransfer::onlyTrashed()->findOrFail($id);

        $transfer->restore();

        $transfer->load([
            'fromAccount:id,name,account_type_id,currency_id',
            'toAccount:id,name,account_type_id,currency_id',
            'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
            
        ]);

        return ApiResponse::update(
            'Account transfer restored successfully',
            new AccountTransferResource($transfer)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only administrators can permanently delete account transfers', 403);
        }

        $transfer = AccountTransfer::onlyTrashed()->findOrFail($id);

        $transfer->forceDelete();

        return ApiResponse::delete('Account transfer permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $stats = [
            'total_transfers' => (clone $query)->count(),
            'trashed_transfers' => (clone $query)->onlyTrashed()->count(),
            'total_sent_amount' => round((clone $query)->sum('sent_amount'), 2),
            'total_received_amount' => round((clone $query)->sum('received_amount'), 2),
        ];

        return ApiResponse::show('Account transfer statistics retrieved successfully', $stats);
    }

    private function query(Request $request)
    {
        $query = AccountTransfer::query()
            ->with([
                'fromAccount:id,name,account_type_id,currency_id',
                'toAccount:id,name,account_type_id,currency_id',
                'fromCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'toCurrency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type'
                
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('from_account_id')) {
            $query->where('from_account_id', $request->from_account_id);
        }

        if ($request->has('to_account_id')) {
            $query->where('to_account_id', $request->to_account_id);
        }

        if ($request->has('from_currency_id')) {
            $query->where('from_currency_id', $request->from_currency_id);
        }

        if ($request->has('to_currency_id')) {
            $query->where('to_currency_id', $request->to_currency_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('sent_amount')) {
            $query->where('sent_amount', $request->sent_amount);
        }

        if ($request->has('received_amount')) {
            $query->where('received_amount', $request->received_amount);
        }

        if ($request->has('date_from')) {
            $query->fromDate($request->date_from);
        }

        if ($request->has('date_to')) {
            $query->toDate( $request->date_to);
        }

        return $query;
    }
}
