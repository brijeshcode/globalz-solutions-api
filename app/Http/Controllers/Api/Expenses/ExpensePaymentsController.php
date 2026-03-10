<?php

namespace App\Http\Controllers\Api\Expenses;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\ExpensePaymentStoreRequest;
use App\Http\Requests\Api\Expenses\ExpensePaymentUpdateRequest;
use App\Http\Resources\Api\Expenses\ExpensePaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Expenses\ExpensePayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpensePaymentsController extends Controller
{
    use HasPagination;
    /**
     * List all expense payments across all transactions with filters and pagination.
     */
    public function listAll(Request $request): JsonResponse
    {
        $query = ExpensePayment::query()
            ->with([
                'expenseTransaction:id,code,subject,amount',
                'account:id,name',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->filled('expense_transaction_id')) {
            $query->where('expense_transaction_id', $request->input('expense_transaction_id'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->filled('date_from')) {
            $query->fromDate($request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->toDate($request->input('date_to'));
        }

        $payments = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Expense payments retrieved successfully',
            $payments,
            ExpensePaymentResource::class
        );
    }

    /**
     * List all payments for a given expense transaction.
     */
    public function index(ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $payments = $expenseTransaction->payments()
            ->with([
                'account:id,name',
                'currency:id,name,code,symbol',
                'createdBy:id,name',
            ])
            ->latest('date')
            ->get();

        return ApiResponse::show(
            'Expense payments retrieved successfully',
            ExpensePaymentResource::collection($payments)
        );
    }

    /**
     * Record a new payment against an expense transaction.
     */
    public function store(ExpensePaymentStoreRequest $request, ExpenseTransaction $expenseTransaction): JsonResponse
    {
        $data = $request->validated();

        // Prevent overpayment
        if ((float) $data['amount'] > $expenseTransaction->due_amount) {
            return ApiResponse::customError(
                'Payment amount exceeds the outstanding due amount of ' . $expenseTransaction->due_amount,
                422
            );
        }

        $account = Account::with('currency.activeRate')->findOrFail($data['account_id']);

        $data['expense_transaction_id'] = $expenseTransaction->id;
        $data['currency_id']            = $account->currency_id;
        $data['currency_rate']          = $account->currency->activeRate->rate ?? 1;
        $data['amount_usd']             = CurrencyHelper::toUsd(
            $data['currency_id'],
            $data['amount'],
            $data['currency_rate']
        );

        $payment = ExpensePayment::create($data);

        $payment->load([
            'account:id,name',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
        ]);

        return ApiResponse::store(
            'Payment recorded successfully',
            new ExpensePaymentResource($payment)
        );
    }

    /**
     * Show a single payment.
     */
    public function show(ExpenseTransaction $expenseTransaction, ExpensePayment $payment): JsonResponse
    {
        $this->authorizePaymentBelongsToExpense($payment, $expenseTransaction);

        $payment->load([
            'account:id,name',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::show(
            'Payment retrieved successfully',
            new ExpensePaymentResource($payment)
        );
    }

    /**
     * Update a payment (amount, account, date, references).
     * ExpensePayment.updated hook handles balance reversal/re-deduction automatically.
     */
    public function update(ExpensePaymentUpdateRequest $request, ExpenseTransaction $expenseTransaction, ExpensePayment $payment): JsonResponse
    {
        $this->authorizePaymentBelongsToExpense($payment, $expenseTransaction);

        $data = $request->validated();

        // Overpayment check: sum of other payments + new amount must not exceed expense total
        $otherPaid = $expenseTransaction->payments()
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        if ($otherPaid + (float) $data['amount'] > (float) $expenseTransaction->amount) {
            return ApiResponse::customError(
                'Updated payment would exceed the total expense amount of ' . $expenseTransaction->amount,
                422
            );
        }

        // Recalculate currency fields when account or amount changes
        if ($data['account_id'] != $payment->account_id) {
            $account               = Account::with('currency.activeRate')->findOrFail($data['account_id']);
            $data['currency_id']   = $account->currency_id;
            $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;
            $data['amount_usd']    = CurrencyHelper::toUsd($data['currency_id'], $data['amount'], $data['currency_rate']);
        } elseif ((float) $data['amount'] != (float) $payment->amount) {
            $data['amount_usd'] = CurrencyHelper::toUsd($payment->currency_id, $data['amount'], $payment->currency_rate);
        }

        $payment->update($data);

        $payment->load([
            'account:id,name',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::update(
            'Payment updated successfully',
            new ExpensePaymentResource($payment)
        );
    }

    /**
     * Delete (reverse) a payment — restores the amount to the account.
     */
    public function destroy(ExpenseTransaction $expenseTransaction, ExpensePayment $payment): JsonResponse
    {
        $this->authorizePaymentBelongsToExpense($payment, $expenseTransaction);

        $payment->delete();

        return ApiResponse::delete('Payment deleted and amount restored to account successfully');
    }

    /**
     * Update a payment by its own ID (no expense transaction required in URL).
     */
    public function updatePayment(ExpensePaymentUpdateRequest $request, ExpensePayment $payment): JsonResponse
    {
        $data        = $request->validated();
        $expenseTransaction = $payment->expenseTransaction;

        // Overpayment check
        $otherPaid = $expenseTransaction->payments()
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        if ($otherPaid + (float) $data['amount'] > (float) $expenseTransaction->amount) {
            return ApiResponse::customError(
                'Updated payment would exceed the total expense amount of ' . $expenseTransaction->amount,
                422
            );
        }

        // Recalculate currency fields when account or amount changes
        if ($data['account_id'] != $payment->account_id) {
            $account               = Account::with('currency.activeRate')->findOrFail($data['account_id']);
            $data['currency_id']   = $account->currency_id;
            $data['currency_rate'] = $account->currency->activeRate->rate ?? 1;
            $data['amount_usd']    = CurrencyHelper::toUsd($data['currency_id'], $data['amount'], $data['currency_rate']);
        } elseif ((float) $data['amount'] != (float) $payment->amount) {
            $data['amount_usd'] = CurrencyHelper::toUsd($payment->currency_id, $data['amount'], $payment->currency_rate);
        }

        $payment->update($data);

        $payment->load([
            'account:id,name',
            'currency:id,name,code,symbol',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::update(
            'Payment updated successfully',
            new ExpensePaymentResource($payment)
        );
    }

    /**
     * Delete a payment by its own ID (no expense transaction required in URL).
     */
    public function destroyPayment(ExpensePayment $payment): JsonResponse
    {
        $payment->delete();

        return ApiResponse::delete('Payment deleted and amount restored to account successfully');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function authorizePaymentBelongsToExpense(ExpensePayment $payment, ExpenseTransaction $expenseTransaction): void
    {
        if ($payment->expense_transaction_id !== $expenseTransaction->id) {
            abort(404, 'Payment not found for this expense transaction.');
        }
    }
}
