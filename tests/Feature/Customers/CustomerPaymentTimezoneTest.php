<?php

use App\Models\Customers\CustomerPayment;
use Carbon\Carbon;
use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

/**
 * Timezone handling for the transaction `date` column.
 *
 * The calendar day a user picks is the day the transaction belongs to, and it must
 * fall in that month for the monthly/yearly reports — which filter with
 * whereYear() + whereMonth() (see EmployeeBusinessController) — no matter which
 * timezone the app or the user runs in.
 *
 * These guard the month-boundary edge behind the "monthly report shows one
 * transaction less than yearly" issue: it looked correct in Asia/Kolkata (the
 * developer) but was reported wrong by the Asia/Beirut (Lebanon) client.
 */
uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
});

afterEach(function () {
    // Release frozen time and restore a neutral timezone so later tests aren't affected.
    Carbon::setTestNow();
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
});

function useAppTimezone(string $tz): void
{
    config(['app.timezone' => $tz]);
    date_default_timezone_set($tz);
}

it('keeps a date-only payment on the selected calendar day even when "now" is already the next day in the app timezone', function (string $tz) {
    useAppTimezone($tz);

    // 2026-07-31 20:15 UTC is still Jul 31 in Beirut (23:15) but already Aug 1 in Kolkata (01:45).
    // HasDateWithTime appends now()'s time-of-day to the picked date, so this is the risky moment.
    Carbon::setTestNow(Carbon::parse('2026-07-31 20:15:00', 'UTC'));

    // User selects the last day of July (date only — no time component).
    $payment = CustomerPayment::factory()->create([
        'customer_id' => $this->customer->id,
        'date'        => '2026-07-31',
    ]);

    expect($payment->refresh()->date->format('Y-m'))->toBe('2026-07')
        ->and($payment->date->day)->toBe(31);
})->with(['Asia/Beirut', 'Asia/Kolkata', 'UTC']);

it('classifies month-boundary payments into the correct month for the report filter, regardless of app timezone', function (string $tz) {
    useAppTimezone($tz);

    // Last instant of July and first instant of August (explicit times — stored verbatim).
    CustomerPayment::factory()->create(['customer_id' => $this->customer->id, 'date' => '2026-07-31 23:59:59']);
    CustomerPayment::factory()->create(['customer_id' => $this->customer->id, 'date' => '2026-08-01 00:00:01']);

    // The exact filter EmployeeBusinessController::monthly() uses.
    $julyCount   = CustomerPayment::whereYear('date', 2026)->whereMonth('date', 7)->count();
    $augustCount = CustomerPayment::whereYear('date', 2026)->whereMonth('date', 8)->count();

    expect($julyCount)->toBe(1)->and($augustCount)->toBe(1);
})->with(['Asia/Beirut', 'Asia/Kolkata', 'UTC']);

it('stores an API-created payment for the last day of the month in that month under Asia/Beirut', function () {
    useAppTimezone('Asia/Beirut');

    // Late-evening Beirut time on the last day of July — the exact boundary the client hit.
    Carbon::setTestNow(Carbon::parse('2026-07-31 23:30:00', 'Asia/Beirut'));

    $payment = $this->createPaymentViaApi(['date' => '2026-07-31']);

    $julyCount   = CustomerPayment::whereYear('date', 2026)->whereMonth('date', 7)->count();
    $augustCount = CustomerPayment::whereYear('date', 2026)->whereMonth('date', 8)->count();

    expect($payment->date->format('Y-m'))->toBe('2026-07')
        ->and($julyCount)->toBe(1)
        ->and($augustCount)->toBe(0);
});
