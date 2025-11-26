<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Suppliers\SupplierPaymentsStoreRequest;
use App\Http\Requests\Api\Suppliers\SupplierPaymentsUpdateRequest;
use App\Http\Resources\Api\Suppliers\SupplierPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Suppliers\SupplierPayment;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->paymentQuery($request);

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Supplier payments retrieved successfully',
            $payments,
            SupplierPaymentResource::class
        );
    }

    public function store(SupplierPaymentsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payment = SupplierPayment::create($data);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $payment->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            // Upload documents
            $payment->createDocuments($files, [
                'type' => 'supplier_payment_document'
            ]);
        }

        $payment->load([
            'supplier:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'supplierPaymentTerm:id,name,days',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::store('Supplier payment created successfully', new SupplierPaymentResource($payment));
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        $supplierPayment->load([
            'supplier:id,name,code,address,phone,mobile,email',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'supplierPaymentTerm:id,name,days',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::show(
            'Supplier payment retrieved successfully',
            new SupplierPaymentResource($supplierPayment)
        );
    }

    public function update(SupplierPaymentsUpdateRequest $request, SupplierPayment $supplierPayment): JsonResponse
    {
        $supplierPayment->update($request->validated());

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $supplierPayment->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            $supplierPayment->updateDocuments($files, [
                'type' => 'supplier_payment_document'
            ]);
        }
        $supplierPayment->load([
            'supplier:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'supplierPaymentTerm:id,name,days',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update('Supplier payment updated successfully', new SupplierPaymentResource($supplierPayment));
    }

    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        if (!RoleHelper::isSuperAdmin()) {
            return ApiResponse::customError('Only super administrators can delete supplier payments', 403);
        }

        $supplierPayment->delete();

        return ApiResponse::delete('Supplier payment deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = SupplierPayment::onlyTrashed()
            ->with([
                'supplier:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'supplierPaymentTerm:id,name,days',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed supplier payments retrieved successfully',
            $payments,
            SupplierPaymentResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $payment = SupplierPayment::onlyTrashed()->findOrFail($id);

        $payment->restore();

        $payment->load([
            'supplier:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'supplierPaymentTerm:id,name,days',
            'account:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Supplier payment restored successfully',
            new SupplierPaymentResource($payment)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $payment = SupplierPayment::onlyTrashed()->findOrFail($id);

        $payment->forceDelete();

        return ApiResponse::delete('Supplier payment permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->paymentQuery($request);

        $stats = [
            'total_payments' => (clone $query)->count(),
            'total_amount_usd' => (clone $query)->sum('amount_usd'),
        ];

        return ApiResponse::show('Supplier payment statistics retrieved successfully', $stats);
    }

    private function paymentQuery(Request $request)
    {
        $query = SupplierPayment::query()
            ->with([
                'supplier:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'supplierPaymentTerm:id,name,days',
                'account:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
                'documents'

            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }
        if ($request->has('account_id')) {
            $query->byCurrency($request->account_id);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        return $query;
    }
}
