<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Vehicle\Car;
use App\Models\Setups\Vehicle\CarRefill;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.car-refills');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->car     = Car::factory()->create();
    $this->station = GasStation::factory()->create(['balance' => 0]);
    $this->driver  = Employee::factory()->create();
});

describe('Car Refills API', function () {
    it('can create a car refill and updates gas station balance', function () {
        $data = [
            'date'           => '2025-12-25 10:00:00',
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1000,
            'amount'         => 50,
        ];

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'date', 'km_driven', 'km_cost', 'amount', 'car', 'gas_station', 'driver']]);

        $this->assertDatabaseHas('car_refills', [
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'odometer'       => 1000,
            'km_driven'      => 0,
        ]);

        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('auto-generates KM prefixed code', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);

        expect($refill->code)->toStartWith('KM');
    });

    it('calculates km_driven from previous odometer', function () {
        CarRefill::factory()->create([
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1000,
            'date'           => '2025-12-20 10:00:00',
        ]);

        $data = [
            'date'           => '2025-12-25 10:00:00',
            'car_id'         => $this->car->id,
            'gas_station_id' => $this->station->id,
            'driver_id'      => $this->driver->id,
            'odometer'       => 1302,
            'amount'         => 30,
        ];

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), $data);

        $response->assertCreated()->assertJson(['data' => ['km_driven' => 302]]);
    });

    it('km_cost is calculated in response', function () {
        CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'odometer' => 1000, 'date' => '2025-12-20 10:00:00',
        ]);

        $response = $this->postJson(route('setups.vehicles.car-refills.store'), [
            'date' => '2025-12-25 10:00:00', 'car_id' => $this->car->id,
            'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id,
            'odometer' => 1100, 'amount' => 20,
        ]);

        $response->assertCreated();
        $kmCost = $response->json('data.km_cost');
        expect(round((float) $kmCost, 4))->toBe(round(20 / 100, 4));
    });

    it('soft delete reverses gas station balance', function () {
        $refill = CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'amount' => 50,
        ]);
        $this->station->update(['balance' => 50]);

        $response = $this->deleteJson(route('setups.vehicles.car-refills.destroy', $refill));

        $response->assertNoContent();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 0]);
    });

    it('restore re-applies gas station balance', function () {
        $refill = CarRefill::factory()->create([
            'car_id' => $this->car->id, 'gas_station_id' => $this->station->id,
            'driver_id' => $this->driver->id, 'amount' => 50,
        ]);
        $refill->delete();
        $this->station->update(['balance' => 0]);

        $response = $this->patchJson(route('setups.vehicles.car-refills.restore', $refill->id));

        $response->assertOk();
        $this->assertDatabaseHas('gas_stations', ['id' => $this->station->id, 'balance' => 50]);
    });

    it('can list car refills with filters', function () {
        CarRefill::factory()->count(2)->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);

        $response = $this->getJson(route('setups.vehicles.car-refills.index', ['car_id' => $this->car->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('validates required fields', function () {
        $response = $this->postJson(route('setups.vehicles.car-refills.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date', 'car_id', 'gas_station_id', 'driver_id', 'odometer', 'amount']);
    });

    it('can list trashed car refills', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);
        $refill->delete();

        $response = $this->getJson(route('setups.vehicles.car-refills.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can force delete a car refill', function () {
        $refill = CarRefill::factory()->create(['car_id' => $this->car->id, 'gas_station_id' => $this->station->id, 'driver_id' => $this->driver->id]);
        $refill->delete();

        $response = $this->deleteJson(route('setups.vehicles.car-refills.force-delete', $refill->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('car_refills', ['id' => $refill->id]);
    });
});
