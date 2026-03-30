<?php

use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('returns stats with correct structure and values', function () {
    AccountAdjust::factory()->credit()->create(['account_id' => $this->account->id, 'amount' => 1000.00]);
    AccountAdjust::factory()->credit()->create(['account_id' => $this->account->id, 'amount' => 500.00]);
    AccountAdjust::factory()->debit()->create(['account_id' => $this->account->id, 'amount' => 300.00]);

    $stats = $this->getJson(route('accounts.adjusts.stats'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['total_adjustments', 'total_credits', 'total_debits', 'total_credit_amount', 'total_debit_amount', 'trashed_count']])
        ->json('data');

    expect($stats['total_adjustments'])->toBe(3)
        ->and($stats['total_credits'])->toBe(2)
        ->and($stats['total_debits'])->toBe(1)
        ->and((float) $stats['total_credit_amount'])->toBe(1500.0)
        ->and((float) $stats['total_debit_amount'])->toBe(300.0);
});

it('trashed_count reflects soft-deleted records', function () {
    $adjust = AccountAdjust::factory()->create(['account_id' => $this->account->id]);
    $adjust->delete();

    $this->getJson(route('accounts.adjusts.stats'))
        ->assertOk()
        ->assertJson(['data' => ['trashed_count' => 1]]);
});
