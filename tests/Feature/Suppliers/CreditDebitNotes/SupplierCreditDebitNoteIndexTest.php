<?php

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use Tests\Feature\Suppliers\CreditDebitNotes\Concerns\HasSupplierCreditDebitNoteSetup;

uses(HasSupplierCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpSupplierCreditDebitNotes());

it('lists all notes with correct structure', function () {
    $this->createNoteViaApi();

    $this->getJson(route('suppliers.credit-debit-notes.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'note_code', 'date', 'prefix', 'type', 'supplier', 'currency', 'amount', 'amount_usd']],
            'pagination',
        ])
        ->assertJsonCount(1, 'data');
});

it('filters by supplier', function () {
    $other = Supplier::factory()->create(['is_active' => true]);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['supplier_id' => $other->id]);

    $this->getJson(route('suppliers.credit-debit-notes.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->usd()->create(['is_active' => true, 'calculation_type' => 'multiply']);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['currency_id' => $other->id, 'currency_rate' => 1.0, 'amount' => 200.00, 'amount_usd' => 200.00]);

    $this->getJson(route('suppliers.credit-debit-notes.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by type', function () {
    $this->createNoteViaApi();
    $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('suppliers.credit-debit-notes.index', ['type' => 'credit']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by prefix', function () {
    $this->createNoteViaApi(['prefix' => 'SCRN']);
    $this->postJson(route('suppliers.credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('suppliers.credit-debit-notes.index', ['prefix' => 'SCRN']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    $this->createNoteViaApi(['date' => '2025-01-01']);
    $this->createNoteViaApi(['date' => '2025-02-15']);
    $this->createNoteViaApi(['date' => '2025-03-30']);

    $this->getJson(route('suppliers.credit-debit-notes.index', ['start_date' => '2025-02-01', 'end_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by note code', function () {
    $note1 = $this->createNoteViaApi();
    $this->createNoteViaApi();

    $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['search' => $note1->code]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe($note1->code);
});

it('searches by note content', function () {
    $this->createNoteViaApi(['note' => 'Special refund for damaged goods']);
    $this->createNoteViaApi(['note' => 'Regular adjustment']);

    $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['search' => 'damaged goods']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.note'))->toContain('damaged goods');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createNoteViaApi();
    }

    $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
