<?php

namespace App\Http\Controllers\Api\Accounts;

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
        $query = Account::query()
            ->with([
                'accountType:id,name',
                'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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
                'currency:id,name,code,symbol',
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
            'currency:id,name,code,symbol',
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

    public function stats(): JsonResponse
    {
        $stats = [
            'total_accounts' => Account::count(),
            'active_accounts' => Account::where('is_active', true)->count(),
            'inactive_accounts' => Account::where('is_active', false)->count(),
            'trashed_accounts' => Account::onlyTrashed()->count(),
            'accounts_by_type' => Account::with('accountType:id,name')
                ->selectRaw('account_type_id, count(*) as count')
                ->groupBy('account_type_id')
                ->having('count', '>', 0)
                ->get(),
            'accounts_by_currency' => Account::with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
        ];

        return ApiResponse::show('Account statistics retrieved successfully', $stats);
    }
}
