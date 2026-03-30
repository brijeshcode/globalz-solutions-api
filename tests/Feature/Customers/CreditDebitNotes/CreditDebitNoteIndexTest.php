<?php

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Customers\CreditDebitNotes\Concerns\HasCreditDebitNoteSetup;

uses(HasCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpCreditDebitNotes());

it('lists all notes with correct structure', function () {
    $this->createNoteViaApi();

    $this->getJson(route('customers.credit-debit-notes.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'note_code', 'date', 'prefix', 'type', 'customer', 'currency', 'amount', 'amount_usd']],
            'pagination',
        ])
        ->assertJsonCount(1, 'data');
});

it('filters by customer', function () {
    $other = Customer::factory()->create(['is_active' => true]);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['customer_id' => $other->id]);

    $this->getJson(route('customers.credit-debit-notes.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->usd()->create(['is_active' => true, 'calculation_type' => 'multiply']);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['currency_id' => $other->id, 'currency_rate' => 1.0, 'amount' => 200.00, 'amount_usd' => 200.00]);

    $this->getJson(route('customers.credit-debit-notes.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by type', function () {
    $this->createNoteViaApi();
    $this->postJson(route('customers.credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('customers.credit-debit-notes.index', ['type' => 'credit']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by prefix', function () {
    $this->createNoteViaApi(['prefix' => 'CRN']);
    $this->postJson(route('customers.credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('customers.credit-debit-notes.index', ['prefix' => 'CRN']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    $this->createNoteViaApi(['date' => '2025-01-01']);
    $this->createNoteViaApi(['date' => '2025-02-15']);
    $this->createNoteViaApi(['date' => '2025-03-30']);

    $this->getJson(route('customers.credit-debit-notes.index', ['date_from' => '2025-02-01', 'date_to' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by note code', function () {
    $note1 = $this->createNoteViaApi();
    $this->createNoteViaApi();

    $response = $this->getJson(route('customers.credit-debit-notes.index', ['search' => $note1->code]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe($note1->code);
});

it('searches by note content', function () {
    $this->createNoteViaApi(['note' => 'Special refund for damaged goods']);
    $this->createNoteViaApi(['note' => 'Regular adjustment']);

    $response = $this->getJson(route('customers.credit-debit-notes.index', ['search' => 'damaged goods']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.note'))->toContain('damaged goods');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createNoteViaApi();
    }

    $response = $this->getJson(route('customers.credit-debit-notes.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
