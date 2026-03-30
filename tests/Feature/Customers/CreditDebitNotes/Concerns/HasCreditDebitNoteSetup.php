<?php

namespace Tests\Feature\Customers\CreditDebitNotes\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setting;
use App\Models\User;

trait HasCreditDebitNoteSetup
{
    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Currency $currency;

    public function setUpCreditDebitNotes(): void
    {
        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->customer = Customer::factory()->create(['is_active' => true]);
        $this->currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        Setting::create([
            'group_name'  => 'customer_credit_debit_notes',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Credit/debit note code counter',
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    protected function creditNotePayload(array $overrides = []): array
    {
        return array_merge([
            'date'          => '2025-01-15',
            'prefix'        => 'CRN',
            'type'          => 'credit',
            'customer_id'   => $this->customer->id,
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
            'prefix' => 'DBN',
            'type'   => 'debit',
            'note'   => 'Test debit note',
        ], $overrides);
    }

    protected function createNoteViaApi(array $overrides = []): CustomerCreditDebitNote
    {
        $this->postJson(
            route('customers.credit-debit-notes.store'),
            $this->creditNotePayload($overrides)
        )->assertCreated();

        return CustomerCreditDebitNote::latest()->first();
    }
}
