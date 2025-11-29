<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerPaymentOrdersStoreRequest;
use App\Http\Requests\Api\Customers\CustomerPaymentOrdersUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerPaymentOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Traits\HasPagination;
use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPaymentOrdersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->paymentOrderQuery($request);

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer payments retrieved successfully',
            $payments,
            CustomerPaymentOrderResource::class
        );
    }

    public function store(CustomerPaymentOrdersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payment = CustomerPayment::create($data);

        $payment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer payment order created successfully (pending approval)';

        return ApiResponse::store($message, new CustomerPaymentOrderResource($payment));
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        // Check if user is salesman and has access to this customer
        if (RoleHelper::isSalesman()) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $customerPayment->load('customer:id,salesperson_id');
                if ($customerPayment->customer->salesperson_id !== $salesmanEmployee->id) {
                    return ApiResponse::customError('You do not have permission to view this customer payment', 403);
                }
            }
        }

        $customerPayment->load([
            'customer:id,name,code,address,city,mobile',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer payment retrieved successfully',
            new CustomerPaymentOrderResource($customerPayment)
        );
    }

    public function update(CustomerPaymentOrdersUpdateRequest $request, CustomerPayment $customerPayment): JsonResponse
    {
        if ($customerPayment->isApproved()) {
            return ApiResponse::customError('Cannot update approved payments', 422);
        }

        $data = $request->validated();

        $customerPayment->update($data);

        $customerPayment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer payment order updated successfully (pending approval)';

        return ApiResponse::update($message, new CustomerPaymentOrderResource($customerPayment));
    }

    public function destroy(CustomerPayment $customerPayment): JsonResponse
    {
        if ($customerPayment->isApproved()) {
            return ApiResponse::customError('Cannot delete approved payments', 422);
        }

        $customerPayment->delete();

        return ApiResponse::delete('Customer payment order deleted successfully');
    }

    public function approve(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('You do not have permission to approve payments', 403);
        }

        if ($customerPayment->isApproved()) {
            return ApiResponse::customError('Payment is already approved', 422);
        }

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'approve_note' => 'nullable|string|max:1000'
        ]);

        $customerPayment->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
            'account_id' => $request->account_id,
            'approve_note' => $request->approve_note
        ]);

        CustomersHelper::addBalance(Customer::find($customerPayment->customer_id), $customerPayment->amount_usd);
        $customerPayment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'approvedBy:id,name',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer payment approved successfully',
            new CustomerPaymentOrderResource($customerPayment)
        );
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerPayment::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer payments retrieved successfully',
            $payments,
            CustomerPaymentOrderResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $payment = CustomerPayment::onlyTrashed()->findOrFail($id);

        $payment->restore();

        $payment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'approvedBy:id,name',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer payment restored successfully',
            new CustomerPaymentOrderResource($payment)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $payment = CustomerPayment::onlyTrashed()->findOrFail($id);

        $payment->forceDelete();

        return ApiResponse::delete('Customer payment permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->paymentOrderQuery($request);

        $stats = [
            'total_payments' => (clone $query)->pending()->count(),
            'total_amount_usd' => (clone $query)->pending()->sum('amount_usd'),
        ];

        return ApiResponse::show('Customer payment statistics retrieved successfully', $stats);
    }

    private function paymentOrderQuery(Request $request)
    {
        $query = CustomerPayment::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->pending()
            ->searchable($request)
            ->sortable($request);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Filter by salesman if user has salesman role
        if (RoleHelper::isSalesman()) {
            $salesmanEmployee = RoleHelper::getSalesmanEmployee();
            if ($salesmanEmployee) {
                $query->whereHas('customer', function ($q) use ($salesmanEmployee) {
                    $q->where('salesperson_id', $salesmanEmployee->id);
                });
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }
}
