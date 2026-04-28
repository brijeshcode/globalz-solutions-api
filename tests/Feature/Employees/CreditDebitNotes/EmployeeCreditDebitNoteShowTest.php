<?php

use Tests\Feature\Employees\CreditDebitNotes\Concerns\HasEmployeeCreditDebitNoteSetup;

uses(HasEmployeeCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpEmployeeCreditDebitNotes());

it('shows a note with all relationships loaded', function () {
    $note = $this->createNoteViaApi();

    $this->getJson(route('employee-credit-debit-notes.show', $note))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'code',
                'note_code',
                'employee'        => ['id', 'name', 'code'],
                'currency'        => ['id', 'name', 'code', 'symbol'],
                'created_by_user' => ['id', 'name'],
                'updated_by_user' => ['id', 'name'],
            ],
        ]);
});

it('returns 404 for a non-existent note', function () {
    $this->getJson(route('employee-credit-debit-notes.show', 999999))
        ->assertNotFound();
});
