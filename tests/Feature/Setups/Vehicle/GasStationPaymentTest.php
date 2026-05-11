<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\Setups\Vehicle\GasStationPayment;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-station-payments');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->station = GasStation::factory()->create(['balance' => 200]);
    $this->account = Account::factory()->create();
});

describe('Gas Station Payments API', function () {
    it('can create a payment and reduces gas station balance', function () {
        $data = [
            'date'           => '2025-12-25 10:00:00',
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ];

        $response = $this->postJson(route('setups.vehicles.gas-station-payments.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'date', 'amount', 'gas_station', 'account']]);

        $this->assertDatabaseHas('gas_station_payments', ['gas_station_id' => $this->station->id, 'amount' => 100]);
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 100]);
    });

    it('auto-generates GS prefixed code', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        expect($payment->code)->toStartWith('GS');
    });

    it('soft delete restores gas station balance', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ]);
        $this->station->update(['balance' => 100]);

        $response = $this->deleteJson(route('setups.vehicles.gas-station-payments.destroy', $payment));

        $response->assertNoContent();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 200]);
    });

    it('restore reduces gas station balance again', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 100,
        ]);
        $payment->delete();
        $this->station->update(['balance' => 200]);

        $response = $this->patchJson(route('setups.vehicles.gas-station-payments.restore', $payment->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 100]);
    });

    it('can list payments', function () {
        GasStationPayment::factory()->count(2)->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.index'));

        $response->assertOk()->assertJsonStructure(['data' => ['*' => ['id', 'code', 'date', 'amount']], 'pagination']);
    });

    it('can filter payments by gas_station_id', function () {
        $otherStation = GasStation::factory()->create();
        GasStationPayment::factory()->count(2)->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        GasStationPayment::factory()->create(['gas_station_id' => $otherStation->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.index', ['gas_station_id' => $this->station->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.gas-station-payments.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'gas_station_id', 'account_id', 'amount']);
    });

    it('can show a payment', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.show', $payment));

        $response->assertOk()->assertJson(['data' => ['id' => $payment->id]]);
    });

    it('can update payment amount and adjusts balance', function () {
        $payment = GasStationPayment::factory()->create([
            'gas_station_id' => $this->station->id, 'account_id' => $this->account->id, 'amount' => 100,
        ]);
        $this->station->update(['balance' => 100]);

        $response = $this->putJson(route('setups.vehicles.gas-station-payments.update', $payment), [
            'date'           => $payment->date->format('Y-m-d H:i:s'),
            'gas_station_id' => $this->station->id,
            'account_id'     => $this->account->id,
            'amount'         => 150,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('can list trashed payments', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        $payment->delete();

        $response = $this->getJson(route('setups.vehicles.gas-station-payments.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can force delete a payment', function () {
        $payment = GasStationPayment::factory()->create(['gas_station_id' => $this->station->id, 'account_id' => $this->account->id]);
        $payment->delete();

        $response = $this->deleteJson(route('setups.vehicles.gas-station-payments.force-delete', $payment->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('gas_station_payments', ['id' => $payment->id]);
    });
});
