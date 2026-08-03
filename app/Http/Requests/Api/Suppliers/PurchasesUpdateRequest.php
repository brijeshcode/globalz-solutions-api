<?php

namespace App\Http\Requests\Api\Suppliers;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchasesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        $purchaseId = $this->route('purchase') ? $this->route('purchase')->id : null;

        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:PAX,PUR',
            'supplier_id' => 'sometimes|required|integer|exists:suppliers,id',
            'warehouse_id' => 'sometimes|required|integer|exists:warehouses,id',
            'currency_id' => 'sometimes|required|integer|exists:currencies,id',
            // 'account_id' => 'nullable|integer|exists:accounts,id',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'currency_rate' => 'sometimes|required|numeric|min:0.000001|max:999999.999999',

            // Removed auto-calculated fields (calculated by service):
            // - sub_total, sub_total_usd
            // - total, total_usd
            // - final_total, final_total_usd

            'tax_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'tax_usd_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.9999',
            'discount_amount_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'note' => 'nullable|string|max:1000',

            // Purchase items for updates
            'items' => 'required|array|min:1',
            'items.*.id' => [
                'nullable',
                'integer',
                $purchaseId ? Rule::exists('purchase_items', 'id')->where('purchase_id', $purchaseId) : 'exists:purchase_items,id',
            ],
            'items.*.item_id' => 'required_with:items|integer|exists:items,id',
            'items.*.price' => 'required_with:items|numeric|min:0|max:999999.999999',
            'items.*.quantity' => 'required_with:items|integer|min:1|max:100000000',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0|max:999999.999999',
            'items.*.note' => 'nullable|string|max:1000',

            // Purchase expenses
            'expenses'                               => 'nullable|array',
            'expenses.*.id'                          => 'nullable|integer',
            'expenses.*.expense_category_id'         => 'required_with:expenses|integer|exists:expense_categories,id',
            'expenses.*.amount'                      => 'required_with:expenses|numeric|min:0',
            'expenses.*.amount_usd'                  => 'required_with:expenses|numeric|min:0',
            'expenses.*.currency_id'                 => 'required_with:expenses|integer|exists:currencies,id',
            'expenses.*.currency_rate'               => 'required_with:expenses|numeric|min:0.000001',
            'expenses.*.date'                        => 'nullable|date',
            'expenses.*.exclude_from_item_cost'      => 'nullable|boolean',
            'expenses.*.vat_amount'                  => 'nullable|numeric|min:0',
            'expenses.*.vat_amount_usd'              => 'nullable|numeric|min:0',
            'expenses.*.is_paid'                     => 'nullable|boolean',
            'expenses.*.account_id'                  => 'required_if:expenses.*.is_paid,true|nullable|integer|exists:accounts,id',
            'expenses.*.payment_date'                => 'nullable|date',
            'expenses.*.payment_note'                => 'nullable|string|max:1000',

            // Documents
            'documents' => 'nullable|array|max:15',
            'documents.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'warehouse_id.required' => 'Warehouse is required.',
            'currency_id.required' => 'Currency is required.',
            'date.required' => 'Purchase date is required.',
            'currency_rate.required' => 'Currency rate is required.',
            'currency_rate.min' => 'Currency rate must be greater than 0.',
            'items.*.id.exists' => 'The selected purchase item does not belong to this purchase.',
            'items.*.item_id.required_with' => 'Item is required for each purchase item.',
            'items.*.price.required_with' => 'Price is required for each purchase item.',
            'items.*.quantity.required_with' => 'Quantity is required for each purchase item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.required' => 'At least one item is required for the purchase.',
            'items.min' => 'At least one item is required for the purchase.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
            'documents.*.mimes' => 'Documents must be of type: jpg, jpeg, png, gif, bmp, webp, pdf, doc, docx, txt.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('discount_amount') && $this->discount_amount === null) {
            $mergeData['discount_amount'] = 0;
        }
        if ($this->has('discount_amount_usd') && $this->discount_amount_usd === null) {
            $mergeData['discount_amount_usd'] = 0;
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }
}
