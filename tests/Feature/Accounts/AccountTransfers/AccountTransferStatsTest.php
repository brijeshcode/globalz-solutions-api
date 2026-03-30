<?php

use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('returns transfer statistics with correct structure and values', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->createTransfer(['received_amount' => 1000.00, 'sent_amount' => 950.00]);
    }

    $stats = $this->getJson(route('accounts.transfers.stats'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['total_transfers', 'trashed_transfers', 'total_sent_amount', 'total_received_amount']])
        ->json('data');

    expect($stats['total_transfers'])->toBe(5)
        ->and($stats['total_sent_amount'])->toBe(4750)
        ->and($stats['total_received_amount'])->toBe(5000);
});

it('trashed_transfers reflects soft-deleted records', function () {
    $transfer = $this->createTransfer();
    $transfer->delete();

    $stats = $this->getJson(route('accounts.transfers.stats'))
        ->assertOk()
        ->json('data');

    expect($stats['trashed_transfers'])->toBeGreaterThanOrEqual(1);
});
