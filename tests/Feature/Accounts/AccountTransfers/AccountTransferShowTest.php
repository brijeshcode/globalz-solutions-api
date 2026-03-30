<?php

use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('shows an account transfer', function () {
    $transfer = $this->createTransfer();

    $this->getJson(route('accounts.transfers.show', $transfer))
        ->assertOk()
        ->assertJson([
            'message' => 'Account transfer retrieved successfully',
            'data'    => ['id' => $transfer->id, 'code' => $transfer->code, 'prefix' => $transfer->prefix],
        ]);
});

it('returns relationship data in the response', function () {
    $transfer = $this->createTransfer();

    $data = $this->getJson(route('accounts.transfers.show', $transfer))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'from_account'  => ['id', 'name', 'account_type_id', 'currency_id'],
                'to_account'    => ['id', 'name', 'account_type_id', 'currency_id'],
                'from_currency' => ['id', 'name', 'code', 'symbol', 'calculation_type'],
                'to_currency'   => ['id', 'name', 'code', 'symbol', 'calculation_type'],
                'created_by'    => ['id', 'name'],
                'updated_by'    => ['id', 'name'],
            ],
        ])
        ->json('data');

    expect($data['from_account']['id'])->toBe($this->fromAccount->id)
        ->and($data['to_account']['id'])->toBe($this->toAccount->id)
        ->and($data['from_currency']['code'])->toBe('USD')
        ->and($data['to_currency']['code'])->toBe('EUR');
});

it('returns 404 for a non-existent transfer', function () {
    $this->getJson(route('accounts.transfers.show', 999))->assertNotFound();
});
