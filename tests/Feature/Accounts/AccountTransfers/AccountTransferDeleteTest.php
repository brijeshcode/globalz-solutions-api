<?php

use App\Models\User;
use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('soft deletes an account transfer', function () {
    $transfer = $this->createTransfer();

    $this->deleteJson(route('accounts.transfers.destroy', $transfer))->assertNoContent();

    $this->assertSoftDeleted('account_transfers', ['id' => $transfer->id]);
});

it('lists trashed transfers', function () {
    $transfer = $this->createTransfer();
    $transfer->delete();

    $this->getJson(route('accounts.transfers.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'date', 'code', 'from_account', 'to_account']], 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed transfer', function () {
    $transfer = $this->createTransfer();
    $transfer->delete();

    $this->patchJson(route('accounts.transfers.restore', $transfer->id))->assertOk();

    $this->assertDatabaseHas('account_transfers', ['id' => $transfer->id, 'deleted_at' => null]);
});

it('force deletes a trashed transfer', function () {
    $transfer = $this->createTransfer();
    $transfer->delete();

    $this->deleteJson(route('accounts.transfers.force-delete', $transfer->id))->assertNoContent();

    $this->assertDatabaseMissing('account_transfers', ['id' => $transfer->id]);
});
