<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerPaymentsStoreRequest;
use App\Http\Requests\Api\Customers\CustomerPaymentsUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPaymentsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->paymentQuery($request);
        $query->with([
                'customer:id,name,code,salesperson_id',
                'customer.salesperson',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
        ->sortable($request);
    
        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer payments retrieved successfully',
            $payments,
            CustomerPaymentResource::class
        );
    }

    public function store(CustomerPaymentsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $user = Auth::user();
        
        $data['approved_by'] = $user->id;
        $data['approved_at'] = now();

        $payment = CustomerPayment::create($data);

        $payment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'approvedBy:id,name',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer payment created and approved successfully';

        return ApiResponse::store($message, new CustomerPaymentResource($payment));
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        $customerPayment->load([
            'customer:id,name,code,address,city,mobile',
            'customer.salesperson',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'approvedBy:id,name',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer payment retrieved successfully',
            new CustomerPaymentResource($customerPayment)
        );
    }

    public function update(CustomerPaymentsUpdateRequest $request, CustomerPayment $customerPayment): JsonResponse
    {
        $data = $request->validated();

        $customerPayment->update($data);

        $customerPayment->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'customerPaymentTerm:id,name,days',
            'approvedBy:id,name',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = $customerPayment->isApproved()
            ? 'Customer payment updated and approved successfully'
            : 'Customer payment order updated successfully (pending approval)';

        return ApiResponse::update($message, new CustomerPaymentResource($customerPayment));
    }

    public function destroy(CustomerPayment $customerPayment): JsonResponse
    {
        if (! RoleHelper::canSuperAdmin() && $customerPayment->isApproved()) {
            return ApiResponse::customError('Cannot delete approved payments', 422);
        }

        $customerPayment->delete();

        return ApiResponse::delete('Customer payment deleted successfully');
    }
 
    public function unapprove(CustomerPayment $customerPayment): JsonResponse
    { 
        return ApiResponse::forbidden(
            'un approve is disabled',
        );
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('You do not have permission to unapprove payments', 403);
        }

        if (!$customerPayment->isApproved()) {
            return ApiResponse::customError('Payment is not approved', 422);
        }

        $customerPayment->update([
            'approved_by' => null,
            'approved_at' => null,
            'account_id' => null,
            'approve_note' => null
        ]);

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
            'Customer payment approval removed successfully',
            new CustomerPaymentResource($customerPayment)
        );
    }

 
    public function trashed(Request $request): JsonResponse
    {
        $query = $this->paymentQuery($request)->onlyTrashed()
            ->with([
                'customer:id,name,code,salesperson_id',
                'customer.salesperson',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->orderBy('deleted_at', 'desc')
            ->sortable($request)
            ;
        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer payments retrieved successfully',
            $payments,
            CustomerPaymentResource::class
        );
    }

    public function showTrashed(int $id): JsonResponse
    {
        $payment = CustomerPayment::onlyTrashed()
            ->with([
                'customer:id,name,code,address,city,mobile',
                'customer.salesperson',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->findOrFail($id);

        return ApiResponse::show(
            'Trashed customer payment retrieved successfully',
            new CustomerPaymentResource($payment)
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
            new CustomerPaymentResource($payment)
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
        $query = $this->paymentQuery($request);
        $stats = [
            'total_payments' => (clone $query)->approved()->count(),
            'total_amount_usd' => (clone $query)->approved()->sum('amount_usd'),
        ];

        return ApiResponse::show('Customer payment statistics retrieved successfully', $stats);
    }

    private function paymentQuery(Request $request)
    {
        $query = CustomerPayment::query()
            
            ->approved()
            ->searchable($request);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('salesperson_id')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('salesperson_id', $request->salesperson_id);
            });
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('date_from')) {
            $query->fromDate($request->date_from);
        }

        if ($request->has('date_to')) {
            $query->toDate($request->date_to);
        }

        if ($request->has('status')) {
            if ($request->status === 'approved') {
                $query->approved();
            } elseif ($request->status === 'pending') {
                $query->pending();
            }
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
