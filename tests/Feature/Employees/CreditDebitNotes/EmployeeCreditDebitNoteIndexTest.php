<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Employees\CreditDebitNotes\Concerns\HasEmployeeCreditDebitNoteSetup;

uses(HasEmployeeCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpEmployeeCreditDebitNotes());

it('lists all notes with correct structure', function () {
    $this->createNoteViaApi();

    $this->getJson(route('employee-credit-debit-notes.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'note_code', 'date', 'prefix', 'type', 'employee', 'currency', 'amount', 'amount_usd']],
            'pagination',
        ])
        ->assertJsonCount(1, 'data');
});

it('filters by employee', function () {
    $other = Employee::factory()->create(['is_active' => true]);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['employee_id' => $other->id]);

    $this->getJson(route('employee-credit-debit-notes.index', ['employee_id' => $this->employee->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->usd()->create(['is_active' => true, 'calculation_type' => 'multiply']);

    $this->createNoteViaApi();
    $this->createNoteViaApi(['currency_id' => $other->id, 'currency_rate' => 1.0, 'amount' => 200.00, 'amount_usd' => 200.00]);

    $this->getJson(route('employee-credit-debit-notes.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by type', function () {
    $this->createNoteViaApi();
    $this->postJson(route('employee-credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('employee-credit-debit-notes.index', ['type' => 'credit']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by prefix', function () {
    $this->createNoteViaApi(['prefix' => 'ECRN']);
    $this->postJson(route('employee-credit-debit-notes.store'), $this->debitNotePayload())->assertCreated();

    $this->getJson(route('employee-credit-debit-notes.index', ['prefix' => 'ECRN']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    $this->createNoteViaApi(['date' => '2025-01-01']);
    $this->createNoteViaApi(['date' => '2025-02-15']);
    $this->createNoteViaApi(['date' => '2025-03-30']);

    $this->getJson(route('employee-credit-debit-notes.index', ['start_date' => '2025-02-01', 'end_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by note code', function () {
    $note1 = $this->createNoteViaApi();
    $this->createNoteViaApi();

    $response = $this->getJson(route('employee-credit-debit-notes.index', ['search' => $note1->code]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe($note1->code);
});

it('searches by note content', function () {
    $this->createNoteViaApi(['note' => 'Bonus adjustment for overtime']);
    $this->createNoteViaApi(['note' => 'Regular deduction']);

    $response = $this->getJson(route('employee-credit-debit-notes.index', ['search' => 'overtime']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.note'))->toContain('overtime');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createNoteViaApi();
    }

    $response = $this->getJson(route('employee-credit-debit-notes.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
