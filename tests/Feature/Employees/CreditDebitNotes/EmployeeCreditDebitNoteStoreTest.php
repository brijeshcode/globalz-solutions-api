<?php

use App\Models\Employees\Employee;
use Tests\Feature\Employees\CreditDebitNotes\Concerns\HasEmployeeCreditDebitNoteSetup;

uses(HasEmployeeCreditDebitNoteSetup::class);

beforeEach(fn () => $this->setUpEmployeeCreditDebitNotes());

it('creates a credit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'), $this->creditNotePayload())
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'type', 'employee', 'currency']])
        ->assertJsonPath('data.type', 'credit');

    $this->assertDatabaseHas('employee_credit_debit_notes', [
        'employee_id' => $this->employee->id,
        'type'        => 'credit',
        'prefix'      => 'ECRN',
    ]);
});

it('creates a debit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'), $this->debitNotePayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'debit');

    $this->assertDatabaseHas('employee_credit_debit_notes', [
        'employee_id' => $this->employee->id,
        'type'        => 'debit',
        'prefix'      => 'EDBN',
    ]);
});

it('accepts ECRX prefix for a credit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'), $this->creditNotePayload(['prefix' => 'ECRX']))
        ->assertCreated();

    $this->assertDatabaseHas('employee_credit_debit_notes', ['prefix' => 'ECRX', 'type' => 'credit']);
});

it('accepts EDBX prefix for a debit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'), $this->debitNotePayload(['prefix' => 'EDBX']))
        ->assertCreated();

    $this->assertDatabaseHas('employee_credit_debit_notes', ['prefix' => 'EDBX', 'type' => 'debit']);
});

it('sets created_by and updated_by to the authenticated user', function () {
    $note = $this->createNoteViaApi();

    expect($note->created_by)->toBe($this->admin->id)
        ->and($note->updated_by)->toBe($this->admin->id);
});

it('auto-generates a 6-digit note code', function () {
    $note = $this->createNoteViaApi();

    expect($note->code)->not()->toBeNull()
        ->and($note->code)->toMatch('/^\d{6}$/');
});

it('rejects a salesman', function () {
    $this->actingAs($this->salesman, 'sanctum');

    $this->postJson(route('employee-credit-debit-notes.store'), $this->creditNotePayload())
        ->assertForbidden();
});

it('requires all mandatory fields', function () {
    $this->postJson(route('employee-credit-debit-notes.store'), [
        'employee_id'   => null,
        'currency_id'   => null,
        'type'          => 'invalid',
        'prefix'        => 'INVALID',
        'amount'        => -100,
        'currency_rate' => 0,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'employee_id', 'currency_id', 'type', 'prefix', 'amount', 'currency_rate']);
});

it('rejects a debit prefix for a credit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'),
        $this->creditNotePayload(['type' => 'credit', 'prefix' => 'EDBN'])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['prefix']);
});

it('rejects a credit prefix for a debit note', function () {
    $this->postJson(route('employee-credit-debit-notes.store'),
        $this->debitNotePayload(['type' => 'debit', 'prefix' => 'ECRN'])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['prefix']);
});

it('rejects amount_usd that does not match currency rate calculation', function () {
    $this->postJson(route('employee-credit-debit-notes.store'),
        $this->creditNotePayload(['amount' => 100.00, 'amount_usd' => 200.00, 'currency_rate' => 1.25])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['amount_usd']);
});

it('rejects an inactive employee', function () {
    $inactive = Employee::factory()->create(['is_active' => false]);

    $this->postJson(route('employee-credit-debit-notes.store'),
        $this->creditNotePayload(['employee_id' => $inactive->id])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['employee_id']);
});
