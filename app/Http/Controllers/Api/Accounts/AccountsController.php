<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\AccountsStoreRequest;
use App\Http\Requests\Api\Accounts\AccountsUpdateRequest;
use App\Http\Resources\Api\Accounts\AccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\TenantFeature;
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

        if (array_key_exists('opening_balance', $data)) {
            $diff = ($data['opening_balance'] ?? 0) - ($account->opening_balance ?? 0);
            $data['current_balance'] = $account->current_balance + $diff;
        }

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
        if(! RoleHelper::canSuperAdmin()){
            return ApiResponse::forbidden('You are not authorized');
        }
        
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
        $accounts = (clone $this->query($request))->with('currency:id,code')->get();
        $isMultiCurrencyEnabled = TenantFeature::isEnabled('multi_currency');

        $privateAccounts    = $accounts->where('is_private', true);
        $nonPrivateAccounts = $accounts->where('is_private', false);

        $stats = [
            'total_accounts'            => $nonPrivateAccounts->count(),
            'total_current_balance_usd' => round($this->sumBalanceInUsd($nonPrivateAccounts->where('include_in_total', true), $isMultiCurrencyEnabled), 2),
            'total_private_accounts'    => $privateAccounts->count(),
            'total_private_balance_usd' => round($this->sumBalanceInUsd($privateAccounts->where('include_in_total', true), $isMultiCurrencyEnabled), 2),
        ];

        return ApiResponse::show('Account statistics retrieved successfully', $stats);
    }

    private function sumBalanceInUsd($accounts, bool $convertCurrency): float
    {
        return $accounts->sum(function ($account) use ($convertCurrency) {
            $balance = $account->current_balance ?? 0;

            if (!$convertCurrency || ($account->currency && strtoupper($account->currency->code) === 'USD')) {
                return $balance;
            }

            return CurrencyHelper::toUsd($account->currency_id, $balance);
        });
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
