<?php

namespace Tests\Feature\Suppliers\SupplierPayments\Concerns;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\Suppliers\SupplierPayment;
use App\Models\User;

trait HasSupplierPaymentSetup
{
    protected User $user;
    protected Supplier $supplier;
    protected Account $account;
    protected Currency $currency;

    public function setUpSupplierPayments(): void
    {
        $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->user, 'sanctum');

        Setting::updateOrCreate(
            ['group_name' => 'supplier_payments', 'key_name' => 'code_counter'],
            ['value' => '1000', 'data_type' => 'number', 'description' => 'Supplier payment code counter']
        );

        $this->supplier = Supplier::factory()->active()->create(['current_balance' => 0]);
        $this->account  = Account::factory()->create(['current_balance' => 10000, 'is_active' => true]);
        $this->currency = Currency::where('code', 'EUR')->first()
            ?? Currency::factory()->eur()->create(['is_active' => true]);
    }

    protected function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'date'          => '2025-01-15',
            'supplier_id'   => $this->supplier->id,
            'account_id'    => $this->account->id,
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.0,
            'amount'        => 500.00,
            'amount_usd'    => 500.00,
        ], $overrides);
    }

    protected function createPaymentViaApi(array $overrides = []): SupplierPayment
    {
        $response = $this->postJson(route('suppliers.payments.store'), $this->paymentPayload($overrides));
        $response->assertCreated();

        return SupplierPayment::find($response->json('data.id'));
    }
}
