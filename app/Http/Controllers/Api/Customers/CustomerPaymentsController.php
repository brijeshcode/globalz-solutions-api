<?php

namespace App\Http\Controllers\Api\Customers;

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
        $query = CustomerPayment::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code',
                'customerPaymentTerm:id,name,days',
                'approvedBy:id,name',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->approved()
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

        if ($request->has('status')) {
            if ($request->status === 'approved') {
                $query->approved();
            } elseif ($request->status === 'pending') {
                $query->pending();
            }
        }

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
            'currency:id,name,code',
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
            'currency:id,name,code,symbol',
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
        if ($customerPayment->isApproved()) {
            return ApiResponse::customError('Cannot update approved payments', 422);
        }

        $data = $request->validated();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        if ($isAdmin && $request->has('account_id') ) {
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        }

        $customerPayment->update($data);

        $customerPayment->load([
            'customer:id,name,code',
            'currency:id,name,code',
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
        if ($customerPayment->isApproved()) {
            return ApiResponse::customError('Cannot delete approved payments', 422);
        }

        $customerPayment->delete();

        return ApiResponse::delete('Customer payment deleted successfully');
    }
 
    public function unapprove(CustomerPayment $customerPayment): JsonResponse
    { 
        abort(403);die;
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
            'currency:id,name,code',
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
        $query = CustomerPayment::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code',
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
            CustomerPaymentResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $payment = CustomerPayment::onlyTrashed()->findOrFail($id);

        $payment->restore();

        $payment->load([
            'customer:id,name,code',
            'currency:id,name,code',
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

    public function stats(): JsonResponse
    {
        $stats = [
            'total_payments' => CustomerPayment::count(),
            'pending_payments' => CustomerPayment::pending()->count(),
            'approved_payments' => CustomerPayment::approved()->count(),
            'trashed_payments' => CustomerPayment::onlyTrashed()->count(),
            'total_amount' => CustomerPayment::approved()->sum('amount'),
            'total_amount_usd' => CustomerPayment::approved()->sum('amount_usd'),
            'payments_by_prefix' => CustomerPayment::selectRaw('prefix, count(*) as count, sum(amount) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'payments_by_currency' => CustomerPayment::with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(amount) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
            'recent_approved' => CustomerPayment::approved()
                ->with(['customer:id,name,code', 'approvedBy:id,name'])
                ->orderBy('approved_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ApiResponse::show('Customer payment statistics retrieved successfully', $stats);
    }
}
