<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnsStoreRequest;
use App\Http\Requests\Api\Customers\CustomerReturnsUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerReturnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Customers\SaleItems;
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

    /**
     * Prepare return item data from sale item
     */
    private function prepareReturnItemData(array $itemInput, float $currencyRate): array
    {
        $saleItem = SaleItems::with(['sale', 'item'])->findOrFail($itemInput['sale_item_id']);
        $returnQuantity = $itemInput['quantity'];

        // Copy all data from sale item and recalculate based on return quantity
        return [
            'item_code' => $saleItem->item_code,
            'item_id' => $saleItem->item_id,
            'sale_id' => $saleItem->sale_id,
            'sale_item_id' => $saleItem->id,
            'quantity' => $returnQuantity,

            // Prices (per unit from sale)
            'price' => $saleItem->price,
            'price_usd' => $saleItem->price_usd,

            // Tax details
            'tax_percent' => $saleItem->tax_percent,
            'tax_label' => $saleItem->tax_label ?? 'TVA',
            'tax_amount' => $saleItem->tax_amount,
            'tax_amount_usd' => $saleItem->tax_amount_usd,

            // TTC price (per unit)
            'ttc_price' => $saleItem->ttc_price,
            'ttc_price_usd' => $saleItem->ttc_price_usd,

            // Discount details
            'discount_percent' => $saleItem->discount_percent,
            'unit_discount_amount' => $saleItem->unit_discount_amount,
            'unit_discount_amount_usd' => $saleItem->unit_discount_amount_usd,

            // Calculate total discount amount for return quantity
            'discount_amount' => $saleItem->unit_discount_amount * $returnQuantity,
            'discount_amount_usd' => $saleItem->unit_discount_amount_usd * $returnQuantity,

            // Calculate total prices for return quantity
            'total_price' => $saleItem->price * $returnQuantity - ($saleItem->unit_discount_amount * $returnQuantity),
            'total_price_usd' => $saleItem->price_usd * $returnQuantity - ($saleItem->unit_discount_amount_usd * $returnQuantity),

            // Calculate return profit (negative because it's a return)
            'total_profit' => ($saleItem->price_usd * $returnQuantity - ($saleItem->unit_discount_amount_usd * $returnQuantity)) - ($saleItem->cost_price * $returnQuantity),

            // Volume and weight
            'total_volume_cbm' => ($saleItem->item->volume_cbm ?? 0) * $returnQuantity,
            'total_weight_kg' => ($saleItem->item->weight_kg ?? 0) * $returnQuantity,

            // Note
            'note' => $itemInput['note'] ?? null,
        ];
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

        if (RoleHelper::isWarehouseManager()) {
            $employee = RoleHelper::getWarehouseEmployee();
            if (! $employee) {
                return $query->whereRaw('1 = 0');
            }
            $warehouseIds = $employee->warehouses()->pluck('warehouses.id');
            if ($warehouseIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            if ($request->has('warehouse_id')) {
                // Only allow filtering by warehouse_id if it's in their assigned warehouses
                if ($warehouseIds->contains($request->warehouse_id)) {
                    $query->byWarehouse($request->warehouse_id);
                } else {
                    $query->whereIn('warehouse_id', $warehouseIds);
                }
            } else {
                $query->whereIn('warehouse_id', $warehouseIds);
            }
        }elseif ($request->has('warehouse_id')) {
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
            return ApiResponse::customError(
                'Only admin users can create direct approved returns',
                403
            );
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $user, &$customerReturn) {
            // Extract items data
            $itemsInput = $data['items'];
            unset($data['items']);

            // Add approval data
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();

            // Create the return order (auto-approved)
            $customerReturn = CustomerReturn::create($data);

            // Disable activity logging for initial return items
            // We don't want to log items created with the return
            CustomerReturnItem::disableLogging();

            // Prepare and create return items from sale items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->prepareReturnItemData($itemInput, $data['currency_rate']);
                $customerReturn->items()->create($itemData);
            }

            // Re-enable activity logging
            CustomerReturnItem::enableLogging();

            // Recalculate return totals
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'items.item:id,short_name,code',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::store(
            'Customer return created and approved successfully',
            new CustomerReturnResource($customerReturn)
        );
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

    public function update(CustomerReturnsUpdateRequest $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can update customer returns', 403);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot update returns that have been received', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $customerReturn) {
            // Extract items data
            $itemsInput = $data['items'];
            $currencyRate = $data['currency_rate'] ?? $customerReturn->currency_rate;
            unset($data['items']);

            // Update the return order
            $customerReturn->update($data);

            // Get IDs of items in the request
            $requestItemIds = collect($itemsInput)->pluck('id')->filter()->toArray();

            // Delete items that are not in the request
            $customerReturn->items()->whereNotIn('id', $requestItemIds)->delete();

            // Update or create items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->prepareReturnItemData($itemInput, $currencyRate);

                if (isset($itemInput['id']) && $itemInput['id']) {
                    // Update existing item
                    $customerReturn->items()->where('id', $itemInput['id'])->update($itemData);
                } else {
                    // Create new item
                    $customerReturn->items()->create($itemData);
                }
            }

            // Recalculate return totals
            $customerReturn->refresh();
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'returnReceivedBy:id,name',
            'items.item:id,short_name,description,code',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return updated successfully',
            new CustomerReturnResource($customerReturn)
        );
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
