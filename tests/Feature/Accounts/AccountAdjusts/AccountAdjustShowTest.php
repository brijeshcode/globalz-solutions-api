<?php

use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('shows an account adjustment', function () {
    $adjust = AccountAdjust::factory()->create(['account_id' => $this->account->id]);

    $this->getJson(route('accounts.adjusts.show', $adjust))
        ->assertOk()
        ->assertJson(['data' => ['id' => $adjust->id, 'type' => $adjust->type]]);
});

it('returns 404 for a non-existent adjustment', function () {
    $this->getJson(route('accounts.adjusts.show', 99999))
        ->assertNotFound();
});
