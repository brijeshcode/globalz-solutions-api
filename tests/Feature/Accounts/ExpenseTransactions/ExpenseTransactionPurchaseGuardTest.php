<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

beforeEach(function () {
    $this->setUpPurchases();
});

function createPurchaseLinkedExpense(): ExpenseTransaction
{
    $parent = ExpenseCategory::firstOrCreate(
        ['name' => 'Purchase Expenses'],
        ['is_active' => true, 'is_system' => true]
    );
    $category = ExpenseCategory::firstOrCreate(
        ['name' => 'Shipping', 'parent_id' => $parent->id],
        ['is_active' => true, 'parent_id' => $parent->id]
    );

    $expenseTx = ExpenseTransaction::factory()->create(['expense_category_id' => $category->id]);

    $purchase = Purchase::factory()->create();

    PurchaseExpense::create([
        'purchase_id'            => $purchase->id,
        'expense_transaction_id' => $expenseTx->id,
        'exclude_from_item_cost' => false,
    ]);

    return $expenseTx;
}

it('blocks editing an expense linked to a purchase', function () {
    $expenseTx = createPurchaseLinkedExpense();

    // Must send valid payload so FormRequest validation passes before guard fires
    $payload = [
        'date'                => '2025-08-31',
        'expense_month'       => '2025-08',
        'expense_category_id' => $expenseTx->expense_category_id,
        'amount'              => 100.00,
        'subject'             => 'changed',
    ];

    $this->putJson(route('expense-transactions.update', $expenseTx), $payload)
        ->assertForbidden()
        ->assertSee('purchase');
});

it('blocks deleting an expense linked to a purchase', function () {
    $expenseTx = createPurchaseLinkedExpense();

    $this->deleteJson(route('expense-transactions.destroy', $expenseTx))
        ->assertForbidden()
        ->assertSee('purchase');
});

it('blocks restoring an expense linked to a purchase', function () {
    $expenseTx = createPurchaseLinkedExpense();
    $expenseTx->delete();

    $this->patchJson(route('expense-transactions.restore', $expenseTx->id))
        ->assertForbidden()
        ->assertSee('purchase');
});

it('blocks force deleting an expense linked to a purchase', function () {
    $expenseTx = createPurchaseLinkedExpense();
    $expenseTx->delete();

    $this->deleteJson(route('expense-transactions.force-delete', $expenseTx->id))
        ->assertForbidden()
        ->assertSee('purchase');
});
