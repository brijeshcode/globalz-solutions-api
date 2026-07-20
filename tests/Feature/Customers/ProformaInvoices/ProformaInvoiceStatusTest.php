<?php

use App\Models\Customers\ProformaInvoice;
use Tests\Feature\Customers\ProformaInvoices\Concerns\HasProformaSetup;

uses(HasProformaSetup::class);

beforeEach(function () {
    $this->setUpProforma();
    $this->actingAs($this->admin, 'sanctum');
});

it('changes status from Draft to Sent', function () {
    $proforma = $this->createProforma(['status' => 'Draft']);

    $this->patchJson(route('proforma-invoices.changeStatus', $proforma), ['status' => 'Sent'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Sent');

    expect($proforma->fresh()->status)->toBe('Sent');
    expect($proforma->fresh()->statusHistories()->where('status', 'Sent')->exists())->toBeTrue();
});

it('changes status from Sent to Accepted', function () {
    $proforma = $this->createProforma(['status' => 'Sent']);

    $this->patchJson(route('proforma-invoices.changeStatus', $proforma), ['status' => 'Accepted'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Accepted');
});

it('changes status to Rejected', function () {
    $proforma = $this->createProforma(['status' => 'Sent']);

    $this->patchJson(route('proforma-invoices.changeStatus', $proforma), ['status' => 'Rejected'])
        ->assertOk()
        ->assertJsonPath('data.status', 'Rejected');
});

it('rejects invalid status value', function () {
    $proforma = $this->createProforma();

    $this->patchJson(route('proforma-invoices.changeStatus', $proforma), ['status' => 'Converted'])
        ->assertUnprocessable();
});

it('cannot change status of a converted proforma', function () {
    $proforma = $this->createProforma(['status' => 'Converted', 'converted_at' => now()]);

    $this->patchJson(route('proforma-invoices.changeStatus', $proforma), ['status' => 'Draft'])
        ->assertUnprocessable();
});
