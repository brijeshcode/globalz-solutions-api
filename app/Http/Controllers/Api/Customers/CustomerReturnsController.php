<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnsStoreRequest;
use App\Http\Resources\Api\Customers\CustomerReturnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Services\Customers\CustomerReturnService;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerReturnsController extends Controller
{
    use HasPagination;

    protected $customerReturnService;

    public function __construct(CustomerReturnService $customerReturnService)
    {
        $this->customerReturnService = $customerReturnService;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::query()
            ->with([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->approved()
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own returns
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
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

        if ($request->has('salesperson_id')) {
            $query->where('salesperson_id', $request->salesperson_id);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->has('status')) {
            if ($request->status === 'received') {
                $query->received();
            } elseif ($request->status === 'not_received') {
                $query->notReceived();
            }
        }

        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer returns retrieved successfully',
            $returns,
            CustomerReturnResource::class
        );
    }

    public function store(CustomerReturnsStoreRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if(!$user->isAdmin()){
            return ApiResponse::show(
                'only admin users can create direct payment order'
            );
        }

        DB::beginTransaction();
        try {
            $customerReturn = CustomerReturn::create([
                'date' => $request->date,
                'prefix' => $request->prefix,
                'customer_id' => $request->customer_id,
                'salesperson_id' => $request->salesperson_id,
                'currency_id' => $request->currency_id,
                'warehouse_id' => $request->warehouse_id,
                'total' => $request->total,
                'total_usd' => $request->total_usd,
                'total_volume_cbm' => $request->total_volume_cbm ?? 0,
                'total_weight_kg' => $request->total_weight_kg ?? 0,
                'note' => $request->note,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approve_note' => $request->approve_note,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($request->items as $itemData) {
                CustomerReturnItem::create([
                    'customer_return_id' => $customerReturn->id,
                    'item_code' => $itemData['item_code'],
                    'item_id' => $itemData['item_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'unit_discount_amount' => $itemData['unit_discount_amount'] ?? 0,
                    'tax_percent' => $itemData['tax_percent'] ?? 0,
                    'total_volume_cbm' => $itemData['total_volume_cbm'] ?? 0,
                    'total_weight_kg' => $itemData['total_weight_kg'] ?? 0,
                    'note' => $itemData['note'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            DB::commit();

            $customerReturn->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
                'items'
            ]);

            return ApiResponse::store(
                'Customer return created and approved successfully',
                new CustomerReturnResource($customerReturn)
            );
        } catch (\Exception $e) {
            DB::rollback();
            info('Failed to create customer return: ' . $e->getMessage());
            return ApiResponse::customError('Failed to create customer return: ' . $e->getMessage(), 500);
        }
    }

    public function show(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only view their own returns
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if( is_null($employee) || $customerReturn->salesperson_id != $employee->id){
                return ApiResponse::customError('You can only view your own return', 403);
            }
        }

        // Only show approved returns
        if (!$customerReturn->isApproved()) {
            return ApiResponse::customError('Return is not approved', 404);
        }

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'returnReceivedBy:id,name',
            'items.item:id,short_name,code,description',
            'items.item.itemUnit:id,name,symbol',
            'items.item.taxCode:id,name,code,description,tax_percent',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer return retrieved successfully',
            new CustomerReturnResource($customerReturn)
        );
    }

    public function update(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can update customer returns', 403);
        }

        $request->validate([
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|string|max:50',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'salesperson_id' => 'sometimes|nullable|exists:employees,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'total' => 'sometimes|required|numeric|min:0',
            'total_usd' => 'sometimes|required|numeric|min:0',
            'total_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'total_weight_kg' => 'sometimes|nullable|numeric|min:0',
            'note' => 'sometimes|nullable|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'sometimes|nullable|exists:customer_return_items,id',
            'items.*.item_code' => 'required_with:items|string',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.price' => 'required_with:items|numeric|min:0',
        ]);

        try {
            $data = $request->only([
                'date', 'prefix', 'customer_id', 'salesperson_id', 'currency_id',
                'warehouse_id', 'total', 'total_usd', 'total_volume_cbm',
                'total_weight_kg', 'note'
            ]);

            $items = $request->input('items', []);

            $customerReturn = $this->customerReturnService->updateCustomerReturnWithItems(
                $customerReturn,
                $data,
                $items
            );

            $customerReturn->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
                'items'
            ]);

            return ApiResponse::update(
                'Customer return updated successfully',
                new CustomerReturnResource($customerReturn)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::customError($e->getMessage(), 422);
        } catch (\Exception $e) {
            return ApiResponse::customError('Failed to update customer return: ' . $e->getMessage(), 500);
        }
    }

    public function markReceived(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isWarehouseManager() && !$user->isAdmin()) {
            return ApiResponse::customError('Only warehouse managers can mark returns as received', 403);
        }

        $request->validate([
            'return_received_note' => 'nullable|string|max:1000'
        ]);

        try {
            // Use service to mark as received and update inventory
            $customerReturn = $this->customerReturnService->markAsReceived(
                $customerReturn,
                $user->id,
                $request->return_received_note
            );

            // Update customer balance
            CustomersHelper::addBalance(
                Customer::find($customerReturn->customer_id),
                $customerReturn->total_usd
            );

            $customerReturn->load([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ]);

            return ApiResponse::update(
                'Customer return marked as received successfully and inventory updated',
                new CustomerReturnResource($customerReturn)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::customError($e->getMessage(), 422);
        } catch (\Exception $e) {
            return ApiResponse::customError('Failed to mark return as received: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can delete returns', 403);
        }

        try {
            $this->customerReturnService->deleteCustomerReturn($customerReturn);

            return ApiResponse::delete('Customer return deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::customError('Failed to delete customer return: ' . $e->getMessage(), 500);
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::onlyTrashed()
            ->with([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->approved()
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

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer returns retrieved successfully',
            $returns,
            CustomerReturnResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can restore returns', 403);
        }

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        // Only restore approved returns
        if (!$return->isApproved()) {
            return ApiResponse::customError('Can only restore approved returns', 422);
        }

        try {
            $this->customerReturnService->restoreCustomerReturn($return);

            $return->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ]);

            return ApiResponse::update(
                'Customer return restored successfully',
                new CustomerReturnResource($return)
            );
        } catch (\Exception $e) {
            return ApiResponse::customError('Failed to restore customer return: ' . $e->getMessage(), 500);
        }
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can permanently delete returns', 403);
        }

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        $return->items()->withTrashed()->forceDelete();
        $return->forceDelete();

        return ApiResponse::delete('Customer return permanently deleted successfully');
    }

    public function stats(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::approved();

        // Role-based filtering for stats: salesman sees only their own stats
        if ($user->isSalesman()) {
            $query->where('salesperson_id', $user->id);
        }

        $stats = [
            'total_returns' => (clone $query)->count(),
            'received_returns' => (clone $query)->received()->count(),
            'not_received_returns' => (clone $query)->notReceived()->count(),
            'trashed_returns' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->sum('total'),
            'total_amount_usd' => (clone $query)->sum('total_usd'),
            'returns_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'returns_by_warehouse' => (clone $query)->with('warehouse:id,name')
                ->selectRaw('warehouse_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('warehouse_id')
                ->having('count', '>', 0)
                ->get(),
            'returns_by_currency' => (clone $query)->with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
            'recent_received' => (clone $query)->received()
                ->with(['customer:id,name,code', 'returnReceivedBy:id,name'])
                ->orderBy('return_received_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ApiResponse::show('Customer return statistics retrieved successfully', $stats);
    }
}
