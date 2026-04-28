<?php

namespace Tests\Feature\Employees\CreditDebitNotes\Concerns;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setting;
use App\Models\User;

trait HasEmployeeCreditDebitNoteSetup
{
    protected User $admin;
    protected User $salesman;
    protected Employee $employee;
    protected Currency $currency;

    public function setUpEmployeeCreditDebitNotes(): void
    {
        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->employee = Employee::factory()->create(['is_active' => true]);
        $this->currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        Setting::create([
            'group_name'  => 'employeeCreditDebitNotes',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Employee credit/debit note code counter',
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    protected function creditNotePayload(array $overrides = []): array
    {
        return array_merge([
            'date'          => '2025-01-15',
            'prefix'        => 'ECRN',
            'type'          => 'credit',
            'employee_id'   => $this->employee->id,
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.25,
            'amount'        => 200.00,
            'amount_usd'    => 250.00,
            'note'          => 'Test credit note',
        ], $overrides);
    }

    protected function debitNotePayload(array $overrides = []): array
    {
        return array_merge($this->creditNotePayload(), [
            'prefix' => 'EDBN',
            'type'   => 'debit',
            'note'   => 'Test debit note',
        ], $overrides);
    }

    protected function createNoteViaApi(array $overrides = []): EmployeeCreditDebitNote
    {
        $this->postJson(
            route('employee-credit-debit-notes.store'),
            $this->creditNotePayload($overrides)
        )->assertCreated();

        return EmployeeCreditDebitNote::latest()->first();
    }
}
