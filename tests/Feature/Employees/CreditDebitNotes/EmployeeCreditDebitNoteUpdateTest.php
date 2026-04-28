<?php

use Tests\Feature\Employees\CreditDebitNotes\Concerns\HasEmployeeCreditDebitNoteSetup;

uses(HasEmployeeCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpEmployeeCreditDebitNotes());

it('updates a note', function () {
    $note = $this->createNoteViaApi();

    $this->putJson(route('employee-credit-debit-notes.update', $note), [
        'amount'     => 300.00,
        'amount_usd' => 375.00,
        'note'       => 'Updated note description',
    ])->assertOk();

    expect($note->fresh()->amount)->toBe('300.00')
        ->and($note->fresh()->note)->toBe('Updated note description');
});

it('sets updated_by to the authenticated user on update', function () {
    $note = $this->createNoteViaApi();

    $this->putJson(route('employee-credit-debit-notes.update', $note), [
        'amount'     => 300.00,
        'amount_usd' => 375.00,
    ])->assertOk();

    expect($note->fresh()->updated_by)->toBe($this->admin->id);
});

it('rejects a salesman attempting to update', function () {
    $note = $this->createNoteViaApi();
    $this->actingAs($this->salesman, 'sanctum');

    $this->putJson(route('employee-credit-debit-notes.update', $note), [
        'amount'     => 300.00,
        'amount_usd' => 375.00,
    ])->assertForbidden();
});

it('rejects mismatched prefix and type on update', function () {
    $note = $this->createNoteViaApi();

    $this->putJson(route('employee-credit-debit-notes.update', $note), [
        'type'   => 'credit',
        'prefix' => 'EDBN',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['prefix']);
});

it('allows partial update with only note field', function () {
    $note = $this->createNoteViaApi();

    $this->putJson(route('employee-credit-debit-notes.update', $note), [
        'note' => 'Partial update note',
    ])->assertOk();

    expect($note->fresh()->note)->toBe('Partial update note');
});
