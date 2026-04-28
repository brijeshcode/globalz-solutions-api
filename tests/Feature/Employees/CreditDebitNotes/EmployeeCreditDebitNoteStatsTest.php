<?php

use Tests\Feature\Employees\CreditDebitNotes\Concerns\HasEmployeeCreditDebitNoteSetup;

uses(HasEmployeeCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpEmployeeCreditDebitNotes());

it('returns correct credit and debit counts with USD totals', function () {
    foreach (range(1, 3) as $ignored) {
        $this->createNoteViaApi(['amount' => 100.00, 'amount_usd' => 125.00]);
    }

    foreach (range(1, 2) as $ignored) {
        $this->postJson(
            route('employee-credit-debit-notes.store'),
            $this->debitNotePayload(['amount' => 150.00, 'amount_usd' => 187.50])
        )->assertCreated();
    }

    $stats = $this->getJson(route('employee-credit-debit-notes.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['total_credit_amount_usd', 'total_debit_amount_usd'],
        ])
        ->json('data');

    expect((float) $stats['total_credit_amount_usd'])->toBe(375.0)
        ->and((float) $stats['total_debit_amount_usd'])->toBe(375.0);
});
