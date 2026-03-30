<?php

use Tests\Feature\Customers\CreditDebitNotes\Concerns\HasCreditDebitNoteSetup;

uses(HasCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpCreditDebitNotes());

// --- Soft Delete ---

it('soft deletes a note', function () {
    $note = $this->createNoteViaApi();

    $this->deleteJson(route('customers.credit-debit-notes.destroy', $note))
        ->assertStatus(204);

    $this->assertSoftDeleted('customer_credit_debit_notes', ['id' => $note->id]);
});

it('prevents a salesman from soft deleting a note', function () {
    $note = $this->createNoteViaApi();
    $this->actingAs($this->salesman, 'sanctum');

    $this->deleteJson(route('customers.credit-debit-notes.destroy', $note))
        ->assertForbidden();

    $this->assertDatabaseHas('customer_credit_debit_notes', ['id' => $note->id, 'deleted_at' => null]);
});

// --- Trashed ---

it('lists trashed notes', function () {
    $note = $this->createNoteViaApi();
    $note->delete();

    $this->getJson(route('customers.credit-debit-notes.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Restore ---

it('restores a trashed note', function () {
    $note = $this->createNoteViaApi();
    $note->delete();

    $this->patchJson(route('customers.credit-debit-notes.restore', $note->id))
        ->assertOk();

    $this->assertDatabaseHas('customer_credit_debit_notes', ['id' => $note->id, 'deleted_at' => null]);
});

it('prevents a salesman from restoring a note', function () {
    $note = $this->createNoteViaApi();
    $note->delete();
    $this->actingAs($this->salesman, 'sanctum');

    $this->patchJson(route('customers.credit-debit-notes.restore', $note->id))
        ->assertForbidden();
});

// --- Force Delete ---

it('permanently deletes a trashed note', function () {
    $note = $this->createNoteViaApi();
    $note->delete();

    $this->deleteJson(route('customers.credit-debit-notes.force-delete', $note->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('customer_credit_debit_notes', ['id' => $note->id]);
});

it('prevents a salesman from permanently deleting a note', function () {
    $note = $this->createNoteViaApi();
    $note->delete();
    $this->actingAs($this->salesman, 'sanctum');

    $this->deleteJson(route('customers.credit-debit-notes.force-delete', $note->id))
        ->assertForbidden();
});
