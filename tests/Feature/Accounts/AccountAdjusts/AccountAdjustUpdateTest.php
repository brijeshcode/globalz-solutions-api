<?php

use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('updates an account adjustment', function () {
    $adjust = AccountAdjust::factory()->create([
        'account_id' => $this->account->id,
        'type'       => 'Credit',
        'amount'     => 500.00,
    ]);

    $this->putJson(route('accounts.adjusts.update', $adjust), $this->adjustPayload(['amount' => 750.00, 'note' => 'Updated note']))
        ->assertOk()
        ->assertJson(['data' => ['amount' => 750.00, 'note' => 'Updated note']]);

    $this->assertDatabaseHas('account_adjusts', ['id' => $adjust->id, 'amount' => '750.00', 'note' => 'Updated note']);
});

it('code is preserved after update', function () {
    $adjust       = AccountAdjust::factory()->create(['account_id' => $this->account->id]);
    $originalCode = $adjust->code;

    $this->putJson(route('accounts.adjusts.update', $adjust), $this->adjustPayload());

    expect($adjust->fresh()->code)->toBe($originalCode);
});
