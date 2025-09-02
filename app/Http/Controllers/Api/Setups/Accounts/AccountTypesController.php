<?php

namespace App\Http\Controllers\Api\Setups\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Accounts\AccountTypesStoreRequest;
use App\Http\Requests\Api\Setups\Accounts\AccountTypesUpdateRequest;
use App\Http\Resources\Api\Setups\Accounts\AccountTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Accounts\AccountType;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTypesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = AccountType::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $accountTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Account types retrieved successfully',
            $accountTypes,
            AccountTypeResource::class
        );
    }

    public function store(AccountTypesStoreRequest $request): JsonResponse
    {
        $accountType = AccountType::create($request->validated());
        $accountType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Account type created successfully',
            new AccountTypeResource($accountType)
        );
    }

    public function show(AccountType $accountType): JsonResponse
    {
        $accountType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Account type retrieved successfully',
            new AccountTypeResource($accountType)
        );
    }

    public function update(AccountTypesUpdateRequest $request, AccountType $accountType): JsonResponse
    {
        $accountType->update($request->validated());
        $accountType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Account type updated successfully',
            new AccountTypeResource($accountType)
        );
    }

    public function destroy(AccountType $accountType): JsonResponse
    {
        $accountType->delete();

        return ApiResponse::delete('Account type deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = AccountType::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $accountTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed account types retrieved successfully',
            $accountTypes,
            AccountTypeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $accountType = AccountType::onlyTrashed()->findOrFail($id);
        $accountType->restore();
        $accountType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Account type restored successfully',
            new AccountTypeResource($accountType)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $accountType = AccountType::onlyTrashed()->findOrFail($id);
        $accountType->forceDelete();

        return ApiResponse::delete('Account type permanently deleted successfully');
    }
}
