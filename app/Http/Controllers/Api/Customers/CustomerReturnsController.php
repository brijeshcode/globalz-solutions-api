<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnsStoreRequest;
use App\Http\Requests\Api\Customers\CustomerReturnsUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerReturnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\SaleItems;
use App\Services\Customers\CustomerReturnService;
use App\Services\Inventory\InventoryService;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerReturnsController extends Controller
{
    use HasPagination;

    protected CustomerReturnService $customerReturnService;

    public function __construct(CustomerReturnService $customerReturnService)
    {
        $this->customerReturnService = $customerReturnService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->customerReturnQuery($request);
        $query->with([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'items.item:id,short_name,code,description',
                // 'items.item.itemUnit:id,name,symbol',
                // 'items.item.taxCode:id,name,code,description,tax_percent',
                'items.sale:id,code,date,prefix',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ]);

        if (!$request->has('sort_by')) {
            $query->orderByRaw("CASE WHEN return_received_by IS NULL THEN 0 ELSE 1 END")
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc');
        } else {
            $query->sortable($request);
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

            // Prepare and create return items from sale items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->customerReturnService->prepareReturnItemData($itemInput, $data['prefix'], $data['currency_rate']);
                $customerReturn->items()->create($itemData);
            }


            // Recalculate return totals
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->subtotal_taxable_amount = $customerReturn->items->sum('total_taxable_amount');
            $customerReturn->subtotal_taxable_amount_usd = $customerReturn->items->sum('total_taxable_amount_usd');
            $customerReturn->total_tax_amount = $customerReturn->items->sum('total_tax_amount');
            $customerReturn->total_tax_amount_usd = $customerReturn->items->sum('total_tax_amount_usd');
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
            'items.item:id,short_name,description,code',
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
            'items.sale:id,code,date,prefix',
            'items.saleItem:id,quantity',
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
        $isReceived   = $customerReturn->isReceived();
        $isSuperAdmin = RoleHelper::canSuperAdmin();

        if ($isReceived && !$isSuperAdmin) {
            return ApiResponse::customError('Only super admins can update received returns', 403);
        }

        $data = $request->validated();

        // Non-super-admin admins: only date and note on non-received returns
        if (!$isSuperAdmin) {
            $customerReturn->update(array_filter([
                'date' => $data['date'] ?? null,
                'note' => $data['note'] ?? null,
            ], fn($v) => !is_null($v)));

            $customerReturn->load([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'returnReceivedBy:id,name',
                'items.item:id,short_name,code,description',
                'items.saleItem',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]);

            return ApiResponse::update('Customer return updated successfully', new CustomerReturnResource($customerReturn));
        }

        // Capture old values before update for balance adjustment
        $oldCustomerId = $customerReturn->customer_id;
        $oldTotalUsd = (float) $customerReturn->total_usd;

        DB::transaction(function () use ($data, $customerReturn, $isReceived, $oldCustomerId, $oldTotalUsd) {
            // Extract items data
            $itemsInput = $data['items'];
            $currencyRate = $data['currency_rate'] ?? $customerReturn->currency_rate;
            unset($data['items']);

            // Snapshot current item quantities before any changes (for re-syncing inventory on received returns)
            $oldItemSnapshots = $isReceived
                ? $customerReturn->items()->get()->map(fn($item) => [
                    'item_id'  => $item->item_id,
                    'quantity' => (int) $item->quantity,
                ])->all()
                : [];

            // Update the return order
            $customerReturn->update($data);

            // Get IDs of items in the request
            $requestItemIds = collect($itemsInput)->pluck('id')->filter()->toArray();

            // Delete items that are not in the request
            $customerReturn->items()->whereNotIn('id', $requestItemIds)->delete();

            // Update or create items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->customerReturnService->prepareReturnItemData($itemInput, $customerReturn->prefix, $currencyRate);

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
            $customerReturn->subtotal_taxable_amount = $customerReturn->items->sum('total_taxable_amount');
            $customerReturn->subtotal_taxable_amount_usd = $customerReturn->items->sum('total_taxable_amount_usd');
            $customerReturn->total_tax_amount = $customerReturn->items->sum('total_tax_amount');
            $customerReturn->total_tax_amount_usd = $customerReturn->items->sum('total_tax_amount_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();

            // Re-sync warehouse inventory for received returns: reverse old quantities, apply new
            if ($isReceived) {
                foreach ($oldItemSnapshots as $old) {
                    if ($old['item_id'] && $old['quantity'] > 0) {
                        InventoryService::subtract($old['item_id'], $customerReturn->warehouse_id, $old['quantity']);
                    }
                }
                foreach ($customerReturn->items as $returnItem) {
                    if ($returnItem->item_id && $returnItem->quantity > 0) {
                        InventoryService::add($returnItem->item_id, $customerReturn->warehouse_id, (int) $returnItem->quantity);
                    }
                }
            }

            // Adjust customer balance when updating a received return (super admin only)
            if ($isReceived) {
                $newTotalUsd = (float) $customerReturn->total_usd;
                $newCustomerId = $customerReturn->customer_id;

                // Reverse old balance from old customer
                CustomersHelper::removeBalance(Customer::find($oldCustomerId), $oldTotalUsd);
                // Apply new balance to new customer (handles customer change + amount change)
                CustomersHelper::addBalance(Customer::find($newCustomerId), $newTotalUsd);
            }
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'returnReceivedBy:id,name',
            'items.item:id,short_name,code,description',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return updated successfully',
            new CustomerReturnResource($customerReturn)
        );
    }

    /**
     * Re-pull each sale-linked return line from its source sale item and recompute totals.
     * Quantities and which lines exist are preserved; only prices/discount/tax/derived
     * values are refreshed. Direct (non-sale-linked) lines are left untouched.
     */
    public function refresh(CustomerReturn $customerReturn): JsonResponse
    {
        $isReceived   = $customerReturn->isReceived();
        $isSuperAdmin = RoleHelper::canSuperAdmin();

        if ($isReceived && !$isSuperAdmin) {
            return ApiResponse::customError('Only super admins can refresh received returns', 403);
        }

        // Direct returns have no source sale to refresh from.
        if (!$customerReturn->items()->whereNotNull('sale_item_id')->exists()) {
            return ApiResponse::customError('This is a direct return with no linked sale to refresh from.', 422);
        }

        $oldCustomerId = $customerReturn->customer_id;
        $oldTotalUsd   = (float) $customerReturn->total_usd;

        $itemChanges   = [];
        $totalsChanges = [];

        DB::transaction(function () use ($customerReturn, $isReceived, $oldCustomerId, $oldTotalUsd, &$itemChanges, &$totalsChanges) {
            foreach ($customerReturn->items as $item) {
                // Skip direct lines and lines whose source sale item is gone.
                if (!$item->sale_item_id || !SaleItems::find($item->sale_item_id)) {
                    continue;
                }

                $original = $item->getOriginal();

                $itemData = $this->customerReturnService->prepareReturnItemData([
                    'sale_item_id' => $item->sale_item_id,
                    'quantity'     => $item->quantity, // preserve the return's own quantity
                    'note'         => $item->note,
                ], $customerReturn->prefix, $customerReturn->currency_rate);

                $item->update($itemData);

                $fields = $this->diffChanges($original, $item->getChanges());
                if ($fields) {
                    $itemChanges[] = [
                        'return_item_id' => $item->id,
                        'item_code'      => $item->item_code,
                        'fields'         => $fields,
                    ];
                }
            }

            // Recalculate return totals
            $customerReturn->refresh();
            $headerOriginal = $customerReturn->getOriginal();
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->subtotal_taxable_amount = $customerReturn->items->sum('total_taxable_amount');
            $customerReturn->subtotal_taxable_amount_usd = $customerReturn->items->sum('total_taxable_amount_usd');
            $customerReturn->total_tax_amount = $customerReturn->items->sum('total_tax_amount');
            $customerReturn->total_tax_amount_usd = $customerReturn->items->sum('total_tax_amount_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();

            $totalsChanges = $this->diffChanges($headerOriginal, $customerReturn->getChanges());

            // Adjust customer balance when refreshing a received return.
            // Quantities are unchanged, so inventory is unaffected; only the USD total can move.
            if ($isReceived) {
                $newTotalUsd = (float) $customerReturn->total_usd;
                CustomersHelper::removeBalance(Customer::find($oldCustomerId), $oldTotalUsd);
                CustomersHelper::addBalance(Customer::find($oldCustomerId), $newTotalUsd);
            }
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'returnReceivedBy:id,name',
            'items.item:id,short_name,code,description',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $hasChanges = !empty($itemChanges) || !empty($totalsChanges);

        return response()->json([
            'message' => $hasChanges
                ? 'Customer return refreshed successfully'
                : 'No changes needed — this return already matches the sale.',
            'data'    => new CustomerReturnResource($customerReturn),
            'changes' => [
                'has_changes' => $hasChanges,
                'items'       => $itemChanges,
                'totals'      => $totalsChanges,
            ],
        ], 200);
    }

    /**
     * Build an old/new diff from a model's getChanges(), dropping bookkeeping columns
     * and normalising numeric strings to floats for a clean response.
     */
    private function diffChanges(array $original, array $changes): array
    {
        $out = [];
        foreach ($changes as $field => $new) {
            if (in_array($field, ['updated_at', 'created_at', 'updated_by'], true)) {
                continue;
            }
            $old = $original[$field] ?? null;
            $out[$field] = [
                'old' => is_numeric($old) ? (float) $old : $old,
                'new' => is_numeric($new) ? (float) $new : $new,
            ];
        }
        return $out;
    }

    public function markReceived(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        if (SettingsHelper::get('sale_settings', 'block_return_sale_received', false)) {
            return ApiResponse::customError('Marking returns as received is currently disabled by the administrator.', 403);
        }

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

        if (!RoleHelper::canSuperAdmin()) {
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

    public function stats(Request $request): JsonResponse
    {
        $query = $this->customerReturnQuery($request);
        $stats = [
            'total_returns' => (clone $query)->count(),
            'received_returns' => (clone $query)->received()->count(),
            'not_received_returns' => (clone $query)->notReceived()->count(),
            'total_amount' => (clone $query)->sum('total'),
            'total_amount_usd' => (clone $query)->sum('total_usd'),
            'total_tax_amount_usd' => (clone $query)->sum('total_tax_amount_usd'),
            // 'trashed_returns' => (clone $query)->onlyTrashed()->count(),
            // 'returns_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('prefix')
            //     ->get(),
            // 'returns_by_warehouse' => (clone $query)->with('warehouse:id,name')
            //     ->selectRaw('warehouse_id, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('warehouse_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'returns_by_currency' => (clone $query)->with('currency:id,name,code')
            //     ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('currency_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'recent_received' => (clone $query)->received()
            //     ->with(['customer:id,name,code', 'returnReceivedBy:id,name'])
            //     ->orderBy('return_received_at', 'desc')
            //     ->limit(5)
            //     ->get(),
        ];

        return ApiResponse::show('Customer return statistics retrieved successfully', $stats);
    }

    private function customerReturnQuery(Request $request)
    {
        $query = CustomerReturn::query()
            ->approved()
            ->searchable($request)
            ;

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

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date);
        }

        if ($request->has('to_date')) {
            $query->toDate($request->to_date);
        }

        if ($request->has('status')) {
            if ($request->status === 'received') {
                $query->received();
            } elseif ($request->status === 'not_received') {
                $query->notReceived();
            }
        }

        return $query;
    }
}
