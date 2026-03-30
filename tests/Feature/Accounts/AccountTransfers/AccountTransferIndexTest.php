<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('lists account transfers with correct structure', function () {
    $this->createTransfer();
    $this->createTransfer();
    $this->createTransfer();

    $this->getJson(route('accounts.transfers.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'date', 'code', 'prefix', 'from_account_id', 'to_account_id', 'from_currency_id', 'to_currency_id', 'received_amount', 'sent_amount', 'currency_rate', 'note', 'from_account', 'to_account', 'from_currency', 'to_currency']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches transfers by code', function () {
    $transfer1 = $this->createTransfer();
    $this->createTransfer();

    $data = $this->getJson(route('accounts.transfers.index', ['search' => $transfer1->code]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe($transfer1->code);
});

it('searches transfers by note', function () {
    $this->createTransfer(['note' => 'Special transfer note']);
    $this->createTransfer(['note' => 'Regular note']);

    $data = $this->getJson(route('accounts.transfers.index', ['search' => 'Special']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['note'])->toBe('Special transfer note');
});

it('handles case-insensitive search', function () {
    $this->createTransfer(['note' => 'UPPERCASE note']);

    $data = $this->getJson(route('accounts.transfers.index', ['search' => 'uppercase']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['note'])->toBe('UPPERCASE note');
});

it('filters by from_account_id', function () {
    $this->createTransfer();
    $other = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency1->id, 'is_active' => true]);
    $this->createTransfer(['from_account_id' => $other->id]);

    $data = $this->getJson(route('accounts.transfers.index', ['from_account_id' => $this->fromAccount->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['from_account']['id'])->toBe($this->fromAccount->id);
});

it('filters by to_account_id', function () {
    $this->createTransfer();
    $other = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency2->id, 'is_active' => true]);
    $this->createTransfer(['to_account_id' => $other->id]);

    $data = $this->getJson(route('accounts.transfers.index', ['to_account_id' => $this->toAccount->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['to_account']['id'])->toBe($this->toAccount->id);
});

it('filters by from_currency_id', function () {
    $this->createTransfer(['from_currency_id' => $this->currency1->id, 'to_currency_id' => $this->currency2->id]);
    $this->createTransfer(['from_currency_id' => $this->currency2->id, 'to_currency_id' => $this->currency1->id]);

    $data = $this->getJson(route('accounts.transfers.index', ['from_currency_id' => $this->currency1->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['from_currency']['id'])->toBe($this->currency1->id);
});

it('filters by to_currency_id', function () {
    $this->createTransfer(['from_currency_id' => $this->currency1->id, 'to_currency_id' => $this->currency2->id]);
    $this->createTransfer(['from_currency_id' => $this->currency2->id, 'to_currency_id' => $this->currency1->id]);

    $data = $this->getJson(route('accounts.transfers.index', ['to_currency_id' => $this->currency2->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['to_currency']['id'])->toBe($this->currency2->id);
});

it('filters by date range', function () {
    $this->createTransfer(['date' => '2025-11-01']);
    $this->createTransfer(['date' => '2025-11-15']);
    $this->createTransfer(['date' => '2025-11-30']);

    $data = $this->getJson(route('accounts.transfers.index', ['start_date' => '2025-11-01', 'end_date' => '2025-11-20']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(2);
});

it('sorts transfers by date ascending', function () {
    $this->createTransfer(['date' => '2025-11-20']);
    $this->createTransfer(['date' => '2025-11-10']);

    $data = $this->getJson(route('accounts.transfers.index', ['sort_by' => 'date', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['date'])->toContain('2025-11-10')
        ->and($data[1]['date'])->toContain('2025-11-20');
});

it('sorts transfers by received_amount ascending', function () {
    $this->createTransfer(['received_amount' => 5000.00]);
    $this->createTransfer(['received_amount' => 1000.00]);

    $data = $this->getJson(route('accounts.transfers.index', ['sort_by' => 'received_amount', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['received_amount'])->toBe('1000.00')
        ->and($data[1]['received_amount'])->toBe('5000.00');
});

it('paginates transfers', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createTransfer();
    }

    $response = $this->getJson(route('accounts.transfers.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});

it('handles multiple filters simultaneously', function () {
    $this->createTransfer(['date' => '2025-11-15']);

    $other = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency1->id, 'is_active' => true]);
    $this->createTransfer(['date' => '2025-11-15', 'from_account_id' => $other->id]);
    $this->createTransfer(['date' => '2025-11-20']);

    $data = $this->getJson(route('accounts.transfers.index', [
        'from_account_id' => $this->fromAccount->id,
        'to_account_id'   => $this->toAccount->id,
        'start_date'      => '2025-11-01',
        'end_date'        => '2025-11-16',
    ]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1);
});

it('returns empty result for non-matching filters', function () {
    $this->createTransfer();

    $data = $this->getJson(route('accounts.transfers.index', ['from_account_id' => 99999]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(0);
});
