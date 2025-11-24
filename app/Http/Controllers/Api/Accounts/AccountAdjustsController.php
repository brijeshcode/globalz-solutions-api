<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Accounts\AccountAdjustsStoreRequest;
use App\Http\Requests\Api\Accounts\AccountAdjustUpdateRequest;
use App\Http\Resources\Api\Accounts\AccountAdjustResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\AccountAdjust;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountAdjustsController extends Controller
{
    use HasPagination;

    public function __construct()
    {
        // Ensure only admin can access this entire controller
        if (!RoleHelper::isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    /**
     * Display a listing of account adjustments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $accountAdjusts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Account adjustments retrieved successfully',
            $accountAdjusts,
            AccountAdjustResource::class
        );
    }

    /**
     * Get trashed account adjustments.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = AccountAdjust::onlyTrashed()
            ->with([
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]);

        // Apply search if provided
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply sorting
        $query->applySorting($request);

        $accountAdjusts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed account adjustments retrieved successfully',
            $accountAdjusts,
            AccountAdjustResource::class
        );
    }

    /**
     * Store a newly created account adjustment.
     */
    public function store(AccountAdjustsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $accountAdjust = AccountAdjust::create($data);

        $accountAdjust->load([
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::store(
            'Account adjustment created successfully',
            new AccountAdjustResource($accountAdjust)
        );
    }

    /**
     * Display the specified account adjustment.
     */
    public function show(AccountAdjust $accountAdjust): JsonResponse
    {
        $accountAdjust->load([
            'account:id,name,current_balance',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::send(
            'Account adjustment retrieved successfully',
            200,
            new AccountAdjustResource($accountAdjust)
        );
    }

    /**
     * Update the specified account adjustment.
     */
    public function update(AccountAdjustUpdateRequest $request, AccountAdjust $accountAdjust): JsonResponse
    {
        $data = $request->validated();

        $accountAdjust->update($data);

        $accountAdjust->load([
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::send(
            'Account adjustment updated successfully',
            200,
            new AccountAdjustResource($accountAdjust)
        );
    }

    /**
     * Soft delete the specified account adjustment.
     */
    public function destroy(AccountAdjust $accountAdjust): JsonResponse
    {
        $accountAdjust->delete();

        return ApiResponse::send(
            'Account adjustment deleted successfully',
            200
        );
    }

    /**
     * Restore a soft-deleted account adjustment.
     */
    public function restore(int $id): JsonResponse
    {
        $accountAdjust = AccountAdjust::onlyTrashed()->findOrFail($id);
        $accountAdjust->restore();

        $accountAdjust->load([
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::send(
            'Account adjustment restored successfully',
            200,
            new AccountAdjustResource($accountAdjust)
        );
    }

    /**
     * Permanently delete a soft-deleted account adjustment.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $accountAdjust = AccountAdjust::onlyTrashed()->findOrFail($id);
        $accountAdjust->forceDelete();

        return ApiResponse::send(
            'Account adjustment permanently deleted',
            200
        );
    }

    /**
     * Get statistics for account adjustments.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = $this->query($request);

        $stats = [
            'total_adjustments' => (clone $query)->count(),
            'total_credits' => (clone $query)->where('type', 'Credit')->count(),
            'total_debits' => (clone $query)->where('type', 'Debit')->count(),
            'total_credit_amount' => round((clone $query)->where('type', 'Credit')->sum('amount'), 2),
            'total_debit_amount' => round((clone $query)->where('type', 'Debit')->sum('amount'), 2),
            'trashed_count' => (clone $query)->onlyTrashed()->count(),
        ];

        return ApiResponse::show(
            'Account adjustment statistics retrieved successfully',
            $stats
        );
    }

    /**
     * Build the base query with filters and relationships.
     */
    private function query(Request $request)
    {
        $query = AccountAdjust::query()
            ->with([
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by account
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('user_id')) {
            $query->where('created_by', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        // Filter by date range (alternative)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Filter by amount
        if ($request->filled('amount')) {
            $query->where('amount', $request->amount);
        }

        // Filter by amount range
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }

        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        return $query;
    }
}
