<?php

use App\Models\Accounts\Account;
use App\Models\Accounts\IncomeTransaction;
use App\Models\Setups\Accounts\IncomeCategory;
use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('lists income transactions with correct structure', function () {
    $this->createTransaction();
    $this->createTransaction();
    $this->createTransaction();

    $this->getJson(route('income-transactions.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data'       => ['*' => ['id', 'date', 'code', 'subject', 'amount', 'income_category', 'account']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches by subject', function () {
    $this->createTransaction(['subject' => 'Searchable Income']);
    $this->createTransaction(['subject' => 'Another Income']);

    $data = $this->getJson(route('income-transactions.index', ['search' => 'Searchable']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['subject'])->toBe('Searchable Income');
});

it('searches by code', function () {
    $t1 = $this->createTransaction();
    $this->createTransaction();

    $data = $this->getJson(route('income-transactions.index', ['search' => $t1->code]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe($t1->code);
});

it('filters by income category', function () {
    $cat1 = IncomeCategory::factory()->create(['name' => 'Sales Revenue']);
    $cat2 = IncomeCategory::factory()->create(['name' => 'Service Income']);

    $this->createTransaction(['income_category_id' => $cat1->id]);
    $this->createTransaction(['income_category_id' => $cat2->id]);

    $data = $this->getJson(route('income-transactions.index', ['income_category_id' => $cat1->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['income_category']['id'])->toBe($cat1->id);
});

it('filters by account', function () {
    $acc1 = Account::factory()->create(['name' => 'Cash Account']);
    $acc2 = Account::factory()->create(['name' => 'Bank Account']);

    $this->createTransaction(['account_id' => $acc1->id]);
    $this->createTransaction(['account_id' => $acc2->id]);

    $data = $this->getJson(route('income-transactions.index', ['account_id' => $acc1->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['account']['id'])->toBe($acc1->id);
});

it('filters by exact date', function () {
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-09-01']);

    $data = $this->getJson(route('income-transactions.index', ['date' => '2025-08-31']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['date'])->toBe('2025-08-31');
});

it('filters by date range', function () {
    $this->createTransaction(['date' => '2025-08-15']);
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-09-15']);

    $this->getJson(route('income-transactions.index', ['date_from' => '2025-08-01', 'date_to' => '2025-08-31']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('sorts by date ascending', function () {
    $this->createTransaction(['date' => '2025-08-31']);
    $this->createTransaction(['date' => '2025-08-15']);

    $data = $this->getJson(route('income-transactions.index', ['sort_by' => 'date', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['date'])->toBe('2025-08-15')
        ->and($data[1]['date'])->toBe('2025-08-31');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createTransaction();
    }

    $response = $this->getJson(route('income-transactions.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
