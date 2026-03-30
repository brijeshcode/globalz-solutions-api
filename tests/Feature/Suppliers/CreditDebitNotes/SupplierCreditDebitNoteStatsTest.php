<?php

use Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup;

uses(HasSupplierCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpSupplierCreditDebitNotes());

it('returns correct counts and USD totals', function () {
    foreach (range(1, 3) as $ignored) {
        $this->createNoteViaApi(['amount' => 100.00, 'amount_usd' => 125.00]);
    }

    foreach (range(1, 2) as $ignored) {
        $this->postJson(
            route('suppliers.credit-debit-notes.store'),
            $this->debitNotePayload(['amount' => 150.00, 'amount_usd' => 187.50])
        )->assertCreated();
    }

    $stats = $this->getJson(route('suppliers.credit-debit-notes.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_notes',
                'credit_notes',
                'debit_notes',
                'trashed_notes',
                'total_credit_amount',
                'total_debit_amount',
                'total_credit_amount_usd',
                'total_debit_amount_usd',
            ],
        ])
        ->json('data');

    expect($stats['total_notes'])->toBe(5)
        ->and($stats['credit_notes'])->toBe(3)
        ->and($stats['debit_notes'])->toBe(2)
        ->and($stats['trashed_notes'])->toBe(0)
        ->and((float) $stats['total_credit_amount_usd'])->toBe(375.0)
        ->and((float) $stats['total_debit_amount_usd'])->toBe(375.0);
});
