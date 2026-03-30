<?php

use App\Models\Setting;
use Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup;

uses(HasSupplierCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpSupplierCreditDebitNotes());

it('generates a credit note code from the current counter value', function () {
    Setting::set('supplier_credit_debit_notes', 'code_counter', 1005, 'number');

    $code = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->creditNotePayload())
        ->assertCreated()
        ->json('data.code');

    expect($code)->toBe('001006');
});

it('generates a debit note code from the current counter value', function () {
    Setting::set('supplier_credit_debit_notes', 'code_counter', 2005, 'number');

    $code = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())
        ->assertCreated()
        ->json('data.code');

    expect($code)->toBe('002006');
});

it('increments the counter sequentially for credit notes', function () {
    $code1 = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->creditNotePayload())
        ->assertCreated()->json('data.code');

    $code2 = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->creditNotePayload())
        ->assertCreated()->json('data.code');

    expect((int) $code2)->toBe((int) $code1 + 1);
});

it('increments the counter sequentially for debit notes', function () {
    $code1 = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())
        ->assertCreated()->json('data.code');

    $code2 = $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())
        ->assertCreated()->json('data.code');

    expect((int) $code2)->toBe((int) $code1 + 1);
});
