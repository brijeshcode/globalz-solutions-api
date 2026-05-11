<?php

use App\Models\Setups\Vehicle\GasStation;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-stations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Gas Stations API', function () {
    it('can list gas stations', function () {
        GasStation::factory()->count(3)->create();

        $response = $this->getJson(route('setups.vehicles.gas-stations.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'balance', 'address', 'note', 'created_by', 'updated_by', 'created_at', 'updated_at']
                ],
                'pagination'
            ]);
    });

    it('can create a gas station', function () {
        $data = ['name' => 'Total Bleibel', 'address' => 'Bleibel, Lebanon', 'note' => 'Main station'];

        $response = $this->postJson(route('setups.vehicles.gas-stations.store'), $data);

        $response->assertCreated()
            ->assertJson(['data' => ['name' => 'Total Bleibel', 'balance' => 0]]);

        $this->assertDatabaseHas('gas_stations', ['name' => 'Total Bleibel']);
    });

    it('can show a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->getJson(route('setups.vehicles.gas-stations.show', $station));

        $response->assertOk()->assertJson(['data' => ['id' => $station->id]]);
    });

    it('can update a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->putJson(route('setups.vehicles.gas-stations.update', $station), [
            'name' => 'Updated Station',
            'address' => 'New Address',
        ]);

        $response->assertOk()->assertJson(['data' => ['name' => 'Updated Station']]);
        $this->assertDatabaseHas('gas_stations', ['id' => $station->id, 'name' => 'Updated Station']);
    });

    it('can delete a gas station', function () {
        $station = GasStation::factory()->create();

        $response = $this->deleteJson(route('setups.vehicles.gas-stations.destroy', $station));

        $response->assertNoContent();
        $this->assertSoftDeleted('gas_stations', ['id' => $station->id]);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.gas-stations.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'address']);
    });

    it('can list trashed gas stations', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->getJson(route('setups.vehicles.gas-stations.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a gas station', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->patchJson(route('setups.vehicles.gas-stations.restore', $station->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $station->id, 'deleted_at' => null]);
    });

    it('can force delete a gas station', function () {
        $station = GasStation::factory()->create();
        $station->delete();

        $response = $this->deleteJson(route('setups.vehicles.gas-stations.force-delete', $station->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('gas_stations', ['id' => $station->id]);
    });

    it('can search gas stations', function () {
        GasStation::factory()->create(['name' => 'Total Bleibel']);
        GasStation::factory()->create(['name' => 'Hypco Hazmieh']);

        $response = $this->getJson(route('setups.vehicles.gas-stations.index', ['search' => 'Total']));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Total Bleibel');
    });
});
