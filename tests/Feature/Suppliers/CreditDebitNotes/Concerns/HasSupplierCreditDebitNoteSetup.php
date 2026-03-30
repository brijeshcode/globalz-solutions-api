<?php

namespace Tests\Feature\Suppliers\CreditDebitNotes\Concerns;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\Setting;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Models\User;

trait HasSupplierCreditDebitNoteSetup
{
    protected User $admin;
    protected User $salesman;
    protected Supplier $supplier;
    protected Currency $currency;

    public function setUpSupplierCreditDebitNotes(): void
    {
        $this->admin    = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->supplier = Supplier::factory()->create(['is_active' => true]);
        $this->currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        Setting::create([
            'group_name'  => 'supplier_credit_debit_notes',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Supplier credit/debit note code counter',
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    protected function creditNotePayload(array $overrides = []): array
    {
        return array_merge([
            'date'          => '2025-01-15',
            'prefix'        => 'SCRN',
            'type'          => 'credit',
            'supplier_id'   => $this->supplier->id,
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
            'prefix' => 'SDRN',
            'type'   => 'debit',
            'note'   => 'Test debit note',
        ], $overrides);
    }

    protected function createNoteViaApi(array $overrides = []): SupplierCreditDebitNote
    {
        $this->postJson(
            route('suppliers.credit-debit-notes.store'),
            $this->creditNotePayload($overrides)
        )->assertCreated();

        return SupplierCreditDebitNote::latest()->first();
    }
}
