<?php

use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('soft deletes an account adjustment', function () {
    $adjust = AccountAdjust::factory()->create(['account_id' => $this->account->id]);

    $this->deleteJson(route('accounts.adjusts.destroy', $adjust))
        ->assertOk();

    $this->assertSoftDeleted('account_adjusts', ['id' => $adjust->id]);
});

it('restores a trashed adjustment', function () {
    $adjust = AccountAdjust::factory()->create(['account_id' => $this->account->id]);
    $adjust->delete();

    $this->patchJson(route('accounts.adjusts.restore', $adjust->id))
        ->assertOk();

    $this->assertDatabaseHas('account_adjusts', ['id' => $adjust->id, 'deleted_at' => null]);
});

it('force deletes a trashed adjustment', function () {
    $adjust = AccountAdjust::factory()->create(['account_id' => $this->account->id]);
    $adjust->delete();

    $this->deleteJson(route('accounts.adjusts.force-delete', $adjust->id))
        ->assertOk();

    $this->assertDatabaseMissing('account_adjusts', ['id' => $adjust->id]);
});
