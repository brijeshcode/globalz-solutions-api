<?php

use App\Models\Accounts\Account;
use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('lists account adjustments with correct structure', function () {
    AccountAdjust::factory()->count(3)->create(['account_id' => $this->account->id]);

    $this->getJson(route('accounts.adjusts.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'date', 'code', 'prefix', 'transfer_code', 'type', 'amount']],
        ])
        ->assertJsonCount(3, 'data');
});

it('filters by type', function () {
    AccountAdjust::factory()->credit()->create(['account_id' => $this->account->id]);
    AccountAdjust::factory()->debit()->create(['account_id' => $this->account->id]);

    $data = $this->getJson(route('accounts.adjusts.index', ['type' => 'Credit']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['type'])->toBe('Credit');
});

it('filters by account', function () {
    $other = Account::factory()->create();
    AccountAdjust::factory()->create(['account_id' => $this->account->id]);
    AccountAdjust::factory()->create(['account_id' => $other->id]);

    $data = $this->getJson(route('accounts.adjusts.index', ['account_id' => $this->account->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['account_id'])->toBe($this->account->id);
});

it('filters by date range', function () {
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'date' => '2025-08-15']);
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'date' => '2025-09-15']);

    $this->getJson(route('accounts.adjusts.index', ['from_date' => '2025-08-01', 'to_date' => '2025-08-31']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by exact amount', function () {
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'amount' => 100.00]);
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'amount' => 500.00]);

    $data = $this->getJson(route('accounts.adjusts.index', ['amount' => 100.00]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and((float) $data[0]['amount'])->toBe(100.0);
});

it('filters by amount range', function () {
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'amount' => 100.00]);
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'amount' => 500.00]);
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'amount' => 1000.00]);

    $data = $this->getJson(route('accounts.adjusts.index', ['amount_from' => 200.00, 'amount_to' => 600.00]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and((float) $data[0]['amount'])->toBe(500.0);
});

it('searches by note', function () {
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'note' => 'Searchable note here']);
    AccountAdjust::factory()->create(['account_id' => $this->account->id, 'note' => 'Different note']);

    $this->getJson(route('accounts.adjusts.index', ['search' => 'Searchable note']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    AccountAdjust::factory()->count(7)->create(['account_id' => $this->account->id]);

    $this->getJson(route('accounts.adjusts.index', ['per_page' => 3]))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect($this->getJson(route('accounts.adjusts.index', ['per_page' => 3]))->json('pagination.total'))->toBe(7);
});

it('returns 403 for non-admin users', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');

    $this->getJson(route('accounts.adjusts.index'))->assertForbidden();
});
