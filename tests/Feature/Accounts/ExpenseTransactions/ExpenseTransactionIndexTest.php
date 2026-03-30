<?php

use App\Models\Accounts\Account;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('lists expense transactions with correct structure', function () {
    $this->createTransaction();
    $this->createTransaction();
    $this->createTransaction();

    $this->getJson(route('expense-transactions.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data'       => ['*' => ['id', 'date', 'code', 'subject', 'amount', 'expense_category', 'account']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches by subject', function () {
    $this->createTransaction(['subject' => 'Searchable Expense']);
    $this->createTransaction(['subject' => 'Another Expense']);

    $data = $this->getJson(route('expense-transactions.index', ['search' => 'Searchable']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['subject'])->toBe('Searchable Expense');
});

it('searches by code', function () {
    $t1 = $this->createTransaction();
    $this->createTransaction();

    $data = $this->getJson(route('expense-transactions.index', ['search' => $t1->code]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe($t1->code);
});

it('filters by expense category', function () {
    $cat1 = ExpenseCategory::factory()->create(['name' => 'Office Supplies']);
    $cat2 = ExpenseCategory::factory()->create(['name' => 'Travel']);

    $this->createTransaction(['expense_category_id' => $cat1->id]);
    $this->createTransaction(['expense_category_id' => $cat2->id]);

    $data = $this->getJson(route('expense-transactions.index', ['expense_category_id' => $cat1->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['expense_category']['id'])->toBe($cat1->id);
});

it('filters by account', function () {
    $acc1 = Account::factory()->create(['name' => 'Cash Account']);
    $acc2 = Account::factory()->create(['name' => 'Bank Account']);

    $this->createTransaction(['account_id' => $acc1->id]);
    $this->createTransaction(['account_id' => $acc2->id]);

    $data = $this->getJson(route('expense-transactions.index', ['account_id' => $acc1->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['account']['id'])->toBe($acc1->id);
});

it('filters by exact date', function () {
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-09-01']);

    $data = $this->getJson(route('expense-transactions.index', ['date' => '2025-08-31']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['date'])->toBe('2025-08-31');
});

it('filters by date range', function () {
    $this->createTransaction(['date' => '2025-08-15']);
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-09-15']);

    $this->getJson(route('expense-transactions.index', ['start_date' => '2025-08-01', 'end_date' => '2025-08-31']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sorts by date ascending', function () {
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-08-15']);

    $data = $this->getJson(route('expense-transactions.index', ['sort_by' => 'date', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['date'])->toBe('2025-08-15')
        ->and($data[1]['date'])->toBe('2025-08-31');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createTransaction();
    }

    $response = $this->getJson(route('expense-transactions.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
