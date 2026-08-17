<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FeatureHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\AccountAdjust;
use App\Models\Accounts\AccountTransfer;
use App\Models\Accounts\IncomeTransaction;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Items\ItemAdjust;
use App\Models\Items\ItemTransfer;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseReturn;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Models\Suppliers\SupplierPayment;
use App\Models\SyncToOld;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Marks transaction records as "copied to the client's legacy system"
 * (the syncin feature). One generic endpoint serves every module; the
 * whitelist below is the single place modules are enumerated.
 */
class SyncinController extends Controller
{
    /**
     * module key (used by the frontend) => model class.
     * The stored `model` value is the model's own table name.
     */
    private const MODULES = [
        'sales'                => Sale::class,
        'customer_payments'    => CustomerPayment::class,
        'customer_returns'     => CustomerReturn::class,
        'customer_credit_debit_notes' => CustomerCreditDebitNote::class,
        'purchases'            => Purchase::class,
        'supplier_payments'    => SupplierPayment::class,
        'purchase_returns'     => PurchaseReturn::class,
        'supplier_credit_debit_notes' => SupplierCreditDebitNote::class,
        'item_transfers'       => ItemTransfer::class,
        'item_adjusts'         => ItemAdjust::class,
        'account_transfers'    => AccountTransfer::class,
        'expense_transactions' => ExpenseTransaction::class,
        'income_transactions'  => IncomeTransaction::class,
        'account_adjusts'      => AccountAdjust::class,
    ];

    public function update(Request $request): JsonResponse
    {
        if (! FeatureHelper::isSyncin()) {
            return ApiResponse::forbidden('The sync-in feature is not enabled for this tenant.');
        }

        if (! RoleHelper::isAdmin()) {
            return ApiResponse::forbidden('Only admin users can update the sync-in flag.');
        }

        $validator = Validator::make($request->all(), [
            'module'    => ['required', 'string', Rule::in(array_keys(self::MODULES))],
            'ids'       => ['required', 'array', 'min:1'],
            'ids.*'     => ['integer'],
            'is_synced' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        $validated = $validator->validated();

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = self::MODULES[$validated['module']];
        $table      = (new $modelClass)->getTable();
        $isSynced   = $validated['is_synced'];

        // Only touch ids that actually exist in the target module.
        $existingIds = $modelClass::whereIn('id', $validated['ids'])->pluck('id')->all();

        if (empty($existingIds)) {
            return ApiResponse::customError('None of the given ids exist in the selected module.', 422);
        }

        foreach ($existingIds as $id) {
            SyncToOld::updateOrCreate(
                ['model' => $table, 'model_id' => $id],
                ['is_synced' => $isSynced]
            );
        }

        return ApiResponse::update('Sync-in flag updated successfully.', [
            'module'      => $validated['module'],
            'is_synced'   => $isSynced,
            'updated_ids' => $existingIds,
            'updated'     => count($existingIds),
        ]);
    }
}
