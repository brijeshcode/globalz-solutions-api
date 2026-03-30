<?php

use App\Models\Setups\Supplier;
use Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup;

uses(HasSupplierCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpSupplierCreditDebitNotes());

it('creates a credit note', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'), $this->creditNotePayload())
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'type', 'supplier', 'currency']])
        ->assertJsonPath('data.type', 'credit');

    $this->assertDatabaseHas('supplier_credit_debit_notes', [
        'supplier_id' => $this->supplier->id,
        'type'        => 'credit',
        'prefix'      => 'SCRN',
    ]);
});

it('creates a debit note', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'debit');

    $this->assertDatabaseHas('supplier_credit_debit_notes', [
        'supplier_id' => $this->supplier->id,
        'type'        => 'debit',
        'prefix'      => 'SDRN',
    ]);
});

it('sets created_by and updated_by to the authenticated user', function () {
    $note = $this->createNoteViaApi();

    expect($note->created_by)->toBe($this->admin->id)
        ->and($note->updated_by)->toBe($this->admin->id);
});

it('auto-generates a 6-digit note code', function () {
    $note = $this->createNoteViaApi();

    expect($note->code)->not()->toBeNull()
        ->and($note->code)->toMatch('/^\d{6}$/');
});

it('rejects a salesman', function () {
    $this->actingAs($this->salesman, 'sanctum');

    $this->postJson(route('suppliers.credit-debit-notes.store'), $this->creditNotePayload())
        ->assertForbidden();
});

it('requires all mandatory fields', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'), [
        'supplier_id'   => null,
        'currency_id'   => null,
        'type'          => 'invalid',
        'prefix'        => 'INVALID',
        'amount'        => -100,
        'currency_rate' => 0,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'supplier_id', 'currency_id', 'type', 'prefix', 'amount', 'currency_rate']);
});

it('rejects a debit prefix (SDRN) for a credit note', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'),
        $this->creditNotePayload(['type' => 'credit', 'prefix' => 'SDRN'])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['prefix']);
});

it('rejects a credit prefix (SCRN) for a debit note', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'),
        $this->debitNotePayload(['type' => 'debit', 'prefix' => 'SCRN'])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['prefix']);
});

it('rejects amount_usd that does not match currency rate calculation', function () {
    $this->postJson(route('suppliers.credit-debit-notes.store'),
        $this->creditNotePayload(['amount' => 100.00, 'amount_usd' => 200.00, 'currency_rate' => 1.25])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['amount_usd']);
});

it('rejects an inactive supplier', function () {
    $inactive = Supplier::factory()->create(['is_active' => false]);

    $this->postJson(route('suppliers.credit-debit-notes.store'),
        $this->creditNotePayload(['supplier_id' => $inactive->id])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['supplier_id']);
});
