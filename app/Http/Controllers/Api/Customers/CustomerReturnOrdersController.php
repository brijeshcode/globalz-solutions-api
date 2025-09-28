<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersStoreRequest;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerReturnOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerReturn;
use App\Models\Employees\Employee;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isNull;

class CustomerReturnOrdersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->pending()
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own returns
        if (ApiHelper::isSalesman()) {
            $employee = ApiHelper::salesmanEmployee();
            if($employee){
                $query->where('salesperson_id', $employee->id);
            }
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer returns retrieved successfully',
            $returns,
            CustomerReturnOrderResource::class
        );
    }

    public function store(CustomerReturnOrdersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, &$return) {
            // Extract items data
            $items = $data['items'];
            unset($data['items']);

            // Create the return order
            $return = CustomerReturn::create($data);

            // Create return items
            foreach ($items as $itemData) {
                $return->items()->create($itemData);
            }
        });

        $return->load([
            'customer:id,name,code',
            'currency:id,name,code',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer return order created successfully (pending approval)';

        return ApiResponse::store($message, new CustomerReturnOrderResource($return));
    }

    public function show(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only view their own returns
        if (ApiHelper::isSalesman()) {
            $employee = ApiHelper::salesmanEmployee();
            if( is_null($employee) || $customerReturn->salesperson_id != $employee->id){
                return ApiResponse::customError('You can only view your own return orders', 403);
            }
        }

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile',
            'currency:id,name,code,symbol',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy',
            'items.item:id,short_name,code,item_unit_id',
            'items.item.itemUnit',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer return retrieved successfully',
            new CustomerReturnOrderResource($customerReturn)
        );
    }

    public function update(CustomerReturnOrdersUpdateRequest $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only update their own returns
        $employee = ApiHelper::salesmanEmployee();
        if ($user->isSalesman() && $customerReturn->salesperson_id !== $employee->id) {
            return ApiResponse::customError('You can only update your own return orders', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Cannot update approved returns', 422);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot update returns that have been received', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $customerReturn) {
            // Extract items data
            $items = $data['items'];
            unset($data['items']);

            // Update the return order
            $customerReturn->update($data);

            // Get IDs of items in the request
            $requestItemIds = collect($items)->pluck('id')->filter()->toArray();

            // Delete items that are not in the request
            $customerReturn->items()->whereNotIn('id', $requestItemIds)->delete();

            // Update or create items
            foreach ($items as $itemData) {
                if (isset($itemData['id']) && $itemData['id']) {
                    // Update existing item
                    $customerReturn->items()->where('id', $itemData['id'])->update($itemData);
                } else {
                    // Create new item
                    $customerReturn->items()->create($itemData);
                }
            }
        });

        $customerReturn->load([
            'customer:id,name,code',
            'currency:id,name,code',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer return order updated successfully (pending approval)';

        return ApiResponse::update($message, new CustomerReturnOrderResource($customerReturn));
    }

    public function destroy(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only delete their own returns
        if ($user->isSalesman() && $customerReturn->salesperson_id !== $user->id) {
            return ApiResponse::customError('You can only delete your own return orders', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Cannot delete approved returns', 422);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot delete returns that have been received', 422);
        }

        DB::transaction(function () use ($customerReturn) {
            // Soft delete all return items first
            $customerReturn->items()->delete();

            // Then soft delete the return order
            $customerReturn->delete();
        });

        return ApiResponse::delete('Customer return order deleted successfully');
    }

    public function approve(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin() ) {
            return ApiResponse::customError('You do not have permission to approve returns', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Return is already approved', 422);
        }

        $request->validate([
            'approve_note' => 'nullable|string|max:1000'
        ]);

        $customerReturn->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approve_note' => $request->approve_note
        ]);

        $customerReturn->load([
            'customer:id,name,code',
            'currency:id,name,code',
            'warehouse:id,name',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return approved successfully',
            new CustomerReturnOrderResource($customerReturn)
        );
    }


    public function trashed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own trashed returns
        if ($user->isSalesman()) {
            $query->where('salesperson_id', $user->id);
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer returns retrieved successfully',
            $returns,
            CustomerReturnOrderResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        // Check if salesman can only restore their own returns
        if ($user->isSalesman() && $return->salesperson_id !== $user->id) {
            return ApiResponse::customError('You can only restore your own return orders', 403);
        }

        $return->restore();

        $return->load([
            'customer:id,name,code',
            'currency:id,name,code',
            'warehouse:id,name',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return restored successfully',
            new CustomerReturnOrderResource($return)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can permanently delete returns', 403);
        }

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($return) {
            // Force delete all return items first (including soft deleted ones)
            $return->items()->withTrashed()->forceDelete();

            // Then force delete the return order
            $return->forceDelete();
        });

        return ApiResponse::delete('Customer return permanently deleted successfully');
    }

    public function stats(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::query();

        // Role-based filtering for stats: salesman sees only their own stats
        if ($user->isSalesman()) {
            $query->where('salesperson_id', $user->id);
        }

        $stats = [
            'total_returns' => (clone $query)->count(),
            'pending_returns' => (clone $query)->pending()->count(),
            'approved_returns' => (clone $query)->approved()->count(),
            'received_returns' => (clone $query)->received()->count(),
            'trashed_returns' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->approved()->sum('total'),
            'total_amount_usd' => (clone $query)->approved()->sum('total_usd'),
            'returns_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'returns_by_currency' => (clone $query)->with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
            'recent_approved' => (clone $query)->approved()
                ->with(['customer:id,name,code', 'approvedBy:id,name'])
                ->orderBy('approved_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ApiResponse::show('Customer return statistics retrieved successfully', $stats);
    }
}
