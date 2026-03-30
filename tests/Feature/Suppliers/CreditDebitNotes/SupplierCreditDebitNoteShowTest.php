<?php

use Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup;

uses(HasSupplierCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpSupplierCreditDebitNotes());

it('shows a note with all relationships loaded', function () {
    $note = $this->createNoteViaApi();

    $this->getJson(route('suppliers.credit-debit-notes.show', $note))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'code',
                'note_code',
                'supplier'        => ['id', 'name', 'code'],
                'currency'        => ['id', 'name', 'code', 'symbol'],
                'created_by_user' => ['id', 'name'],
                'updated_by_user' => ['id', 'name'],
            ],
        ]);
});

it('returns 404 for a non-existent note', function () {
    $this->getJson(route('suppliers.credit-debit-notes.show', 999999))
        ->assertNotFound();
});
