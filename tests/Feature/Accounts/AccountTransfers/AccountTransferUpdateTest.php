<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('updates an account transfer', function () {
    $transfer       = $this->createTransfer();
    $newFromAccount = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency1->id, 'is_active' => true]);

    $this->putJson(route('accounts.transfers.update', $transfer), $this->transferPayload([
        'from_account_id' => $newFromAccount->id,
        'received_amount' => 2000.00,
        'sent_amount'     => 1900.00,
        'note'            => 'Updated transfer',
    ]))
        ->assertOk()
        ->assertJson(['data' => ['received_amount' => 2000.00, 'sent_amount' => 1900.00, 'note' => 'Updated transfer']]);

    $this->assertDatabaseHas('account_transfers', [
        'id'              => $transfer->id,
        'from_account_id' => $newFromAccount->id,
        'received_amount' => 2000.00,
    ]);
});

it('code is preserved after update', function () {
    $transfer = $this->createTransfer();

    $this->putJson(route('accounts.transfers.update', $transfer), $this->transferPayload());

    expect($transfer->fresh()->code)->toBe($transfer->code);
});

it('sets updated_by after update', function () {
    $transfer = $this->createTransfer();
    $transfer->update(['note' => 'Updated note']);

    expect($transfer->fresh()->updated_by)->toBe($this->admin->id);
});
