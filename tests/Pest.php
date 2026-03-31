<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->beforeEach(function () {
        // Skip tenant-related middleware — tenant is already set via makeCurrent() in TestCase::setUpTenant()
        $this->withoutMiddleware([
            \Spatie\Multitenancy\Http\Middleware\NeedsTenant::class,
            \Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession::class,
        ]);

        // Reset static caches that persist across tests within the same process
        \App\Helpers\CurrencyHelper::resetStaticCache();

        // Reset Faker's unique generator so it doesn't carry over used values
        // from previous tests (prevents duplicate entry errors on unique DB columns)
        fake()->unique(true);
    })
    ->in('Feature');

// ─── Customer Credit/Debit Notes ──────────────────────────────────────────────
uses(Tests\Feature\Customers\CreditDebitNotes\Concerns\HasCreditDebitNoteSetup::class)
    ->group('api', 'customers', 'credit-debit-notes')
    ->in('Feature/Customers/CreditDebitNotes');

// ─── Supplier Credit/Debit Notes ──────────────────────────────────────────────
uses(Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup::class)
    ->group('api', 'suppliers', 'credit-debit-notes')
    ->in('Feature/Suppliers/CreditDebitNotes');

// ─── Customer Payment Orders ───────────────────────────────────────────────────
uses(Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup::class)
    ->group('api', 'customers', 'customer-payment-orders')
    ->in('Feature/Customers/PaymentOrders');

// ─── Accounts ─────────────────────────────────────────────────────────────────
uses(Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup::class)
    ->group('api', 'accounts')
    ->in('Feature/Accounts/Accounts');

// ─── Account Adjusts ──────────────────────────────────────────────────────────
uses(Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup::class)
    ->group('api', 'accounts', 'account-adjusts')
    ->in('Feature/Accounts/AccountAdjusts');

// ─── Account Transfers ────────────────────────────────────────────────────────
uses(Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup::class)
    ->group('api', 'accounts', 'account-transfers')
    ->in('Feature/Accounts/AccountTransfers');

// ─── Expense Transactions ─────────────────────────────────────────────────────
uses(Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup::class)
    ->group('api', 'accounts', 'expense-transactions')
    ->in('Feature/Accounts/ExpenseTransactions');

// ─── Income Transactions ──────────────────────────────────────────────────────
uses(Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup::class)
    ->group('api', 'accounts', 'income-transactions')
    ->in('Feature/Accounts/IncomeTransactions');

// ─── Purchases ────────────────────────────────────────────────────────────────
uses(Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup::class)
    ->group('api', 'suppliers', 'purchases')
    ->in('Feature/Suppliers/Purchases');

// ─── Purchase Returns ─────────────────────────────────────────────────────────
uses(Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup::class)
    ->group('api', 'suppliers', 'purchase-returns')
    ->in('Feature/Suppliers/PurchaseReturns');

// ─── Supplier Payments ────────────────────────────────────────────────────────
uses(Tests\Feature\Suppliers\SupplierPayments\Concerns\HasSupplierPaymentSetup::class)
    ->group('api', 'suppliers', 'supplier-payments')
    ->in('Feature/Suppliers/SupplierPayments');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
