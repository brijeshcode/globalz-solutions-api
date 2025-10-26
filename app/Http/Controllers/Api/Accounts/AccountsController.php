<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\AccountsStoreRequest;
use App\Http\Requests\Api\Accounts\AccountsUpdateRequest;
use App\Http\Resources\Api\Accounts\AccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $accounts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Accounts retrieved successfully',
            $accounts,
            AccountResource::class
        );
    }

    public function store(AccountsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['current_balance'] = $data['opening_balance'] ?? 0;
        $account = Account::create($data);

        $account->load([
            'accountType:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store(
            'Account created successfully',
            new AccountResource($account)
        );
    }

    public function show(Account $account): JsonResponse
    {
        $account->load([
            'accountType:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Account retrieved successfully',
            new AccountResource($account)
        );
    }

    public function update(AccountsUpdateRequest $request, Account $account): JsonResponse
    {
        $data = $request->validated();

        $account->update($data);

        $account->load([
            'accountType:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Account updated successfully',
            new AccountResource($account)
        );
    }

    public function destroy(Account $account): JsonResponse
    {
        $account->delete();

        return ApiResponse::delete('Account deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Account::onlyTrashed()
            ->with([
                'accountType:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Apply same filters as index method
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('account_type_id')) {
            $query->where('account_type_id', $request->account_type_id);
        }

        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        $accounts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed accounts retrieved successfully',
            $accounts,
            AccountResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $account = Account::onlyTrashed()->findOrFail($id);
        
        $account->restore();
        
        $account->load([
            'accountType:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Account restored successfully',
            new AccountResource($account)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $account = Account::onlyTrashed()->findOrFail($id);
        
        $account->forceDelete();

        return ApiResponse::delete('Account permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);
        $accounts =  (clone $query)->with('currency:id,code')->get();

        // Calculate total current balance in USD
        $totalCurrentBalanceUsd = 0;

        foreach ($accounts as $account) {
            $balance = $account->current_balance ?? 0;

            // Skip conversion if currency is already USD
            if ($account->currency && strtoupper($account->currency->code) === 'USD') {
                $totalCurrentBalanceUsd += $balance;
            } else {
                $balanceUsd = CurrencyHelper::toUsd(
                    $account->currency_id,
                    $balance
                );
                $totalCurrentBalanceUsd += $balanceUsd;
            }
        }

        $stats = [
            'total_accounts' => $accounts->count(),
            'total_current_balance_usd' => round($totalCurrentBalanceUsd, 2),
        ];

        return ApiResponse::show('Account statistics retrieved successfully', $stats);
    }

    private function query( Request $request)
    {
        $query = Account::query()
            ->with([
                'accountType:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by account type
        if ($request->has('account_type_id')) {
            $query->where('account_type_id', $request->account_type_id);
        }

        // Filter by currency
        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }
        return $query; 
    }
}
