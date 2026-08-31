<?php

/**
 * Tests that customer credit/debit notes fold into the aging report:
 *   - debit notes are treated as invoices (invoice date/age + invoice history)
 *   - credit notes are treated as payments (payment date/age/amount + history)
 *
 * The controller is called directly (no HTTP) — we only need the tenant DB and
 * factories. Notes have no approval concept; they count as soon as they exist.
 */

use App\Http\Controllers\Api\Reports\Customer\CustomerAgingReportController;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Accounts\Account;
use App\Models\Employees\Employee;
use App\Models\Customers\Sale;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Run the report and return the resource row for the given customer (or null).
 */
function agingRowFor(int $customerId, array $params = []): ?array
{
    $response = (new CustomerAgingReportController())->index(new Request($params + ['per_page' => 200]));
    $rows = $response->getData(true)['data'];

    return collect($rows)->firstWhere('customer_id', $customerId);
}

beforeEach(function () {
    $this->currency = Currency::factory()->create(['is_active' => true]);
    $this->approver = User::factory()->create();
});

it('includes a customer whose only activity is credit/debit notes', function () {
    $customer = Customer::factory()->create(['is_active' => true, 'salesperson_id' => null]);

    CustomerCreditDebitNote::factory()->debit()->create([
        'customer_id' => $customer->id,
        'currency_id' => $this->currency->id,
        'date'        => '2025-06-01 09:00:00',
    ]);
    CustomerCreditDebitNote::factory()->credit()->create([
        'customer_id' => $customer->id,
        'currency_id' => $this->currency->id,
        'date'        => '2025-07-01 09:00:00',
    ]);

    $row = agingRowFor($customer->id);

    expect($row)->not->toBeNull()
        ->and($row['last_invoice_date'])->toContain('2025-06-01')
        ->and($row['last_payment_date'])->toContain('2025-07-01');
});

it('lets a newer debit note drive the invoice date and appear in invoice history', function () {
    $customer = Customer::factory()->create(['is_active' => true, 'salesperson_id' => null]);

    Sale::factory()->create([
        'customer_id' => $customer->id,
        'approved_by' => $this->approver->id,
        'date'        => '2025-01-01 09:00:00',
    ]);

    // amount 1484 @ rate 2 => amount_usd 742; asserting 742 proves we return USD.
    CustomerCreditDebitNote::factory()->debit()->withPrefix('DBN')->withAmount(1484.00, 2.0)->create([
        'customer_id' => $customer->id,
        'currency_id' => $this->currency->id,
        'date'        => '2025-08-01 09:00:00',
    ]);

    $row = agingRowFor($customer->id);

    expect($row['last_invoice_date'])->toContain('2025-08-01')
        ->and($row['last_invoice_amount'])->toEqual(742.0);

    $codes = collect($row['invoice_history'])->pluck('code');
    expect($codes)->toContain(CustomerCreditDebitNote::where('customer_id', $customer->id)->first()->note_code);
});

it('lets a newer credit note drive the payment date, amount and history', function () {
    $customer = Customer::factory()->create(['is_active' => true, 'salesperson_id' => null]);

    CustomerPayment::factory()->approved()->create([
        'customer_id' => $customer->id,
        'approved_by' => $this->approver->id,
        'account_id'  => Account::factory()->create()->id,
        'date'        => '2025-01-05 09:00:00',
    ]);

    // amount 642 @ rate 2 => amount_usd 321; asserting 321 proves we return USD.
    CustomerCreditDebitNote::factory()->credit()->withPrefix('CRN')->withAmount(642.00, 2.0)->create([
        'customer_id' => $customer->id,
        'currency_id' => $this->currency->id,
        'date'        => '2025-08-10 09:00:00',
    ]);

    $row = agingRowFor($customer->id);

    expect($row['last_payment_date'])->toContain('2025-08-10')
        ->and($row['last_payment_amount'])->toEqual(321.0);

    $codes = collect($row['payment_history'])->pluck('code');
    expect($codes)->toContain(CustomerCreditDebitNote::where('customer_id', $customer->id)->first()->note_code);
});

it('excludes customers of disabled salespersons by default, but includes them on request', function () {
    $disabledSalesperson = Employee::factory()->create(['is_active' => false]);

    $customer = Customer::factory()->create([
        'is_active'      => true,
        'salesperson_id' => $disabledSalesperson->id,
    ]);

    CustomerCreditDebitNote::factory()->debit()->create([
        'customer_id' => $customer->id,
        'currency_id' => $this->currency->id,
        'date'        => '2025-06-01 09:00:00',
    ]);

    // Default: hidden because the salesperson is disabled.
    expect(agingRowFor($customer->id))->toBeNull();

    // Opt-in: visible when include_disabled_salespersons is set.
    expect(agingRowFor($customer->id, ['include_disabled_salespersons' => true]))->not->toBeNull();
});
