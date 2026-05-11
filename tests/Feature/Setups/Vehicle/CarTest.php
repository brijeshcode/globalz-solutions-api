<?php

use App\Models\Setups\Vehicle\Car;
use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.cars');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Cars API', function () {
    it('can list cars', function () {
        Car::factory()->count(3)->create();

        $response = $this->getJson(route('setups.vehicles.cars.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'plate_number', 'year', 'color', 'make', 'model', 'is_active', 'created_by', 'updated_by']
                ],
                'pagination'
            ]);
    });

    it('can create a car with name only', function () {
        $response = $this->postJson(route('setups.vehicles.cars.store'), ['name' => 'Toyota Hiace']);

        $response->assertCreated()
            ->assertJson(['data' => ['name' => 'Toyota Hiace', 'is_active' => true]]);

        $this->assertDatabaseHas('cars', ['name' => 'Toyota Hiace']);
    });

    it('can create a car with all fields', function () {
        $data = [
            'name'         => 'Toyota Hiace',
            'plate_number' => 'LEB-1234',
            'year'         => 2020,
            'color'        => 'White',
            'make'         => 'Toyota',
            'model'        => 'Hiace',
            'note'         => 'Delivery van',
            'is_active'    => true,
        ];

        $response = $this->postJson(route('setups.vehicles.cars.store'), $data);

        $response->assertCreated()->assertJson(['data' => ['plate_number' => 'LEB-1234', 'year' => 2020]]);
    });

    it('can show a car', function () {
        $car = Car::factory()->create();

        $response = $this->getJson(route('setups.vehicles.cars.show', $car));

        $response->assertOk()->assertJson(['data' => ['id' => $car->id]]);
    });

    it('can update a car', function () {
        $car = Car::factory()->create();

        $response = $this->putJson(route('setups.vehicles.cars.update', $car), ['name' => 'Updated Car', 'is_active' => false]);

        $response->assertOk()->assertJson(['data' => ['name' => 'Updated Car', 'is_active' => false]]);
        $this->assertDatabaseHas('cars', ['id' => $car->id, 'name' => 'Updated Car']);
    });

    it('can delete a car', function () {
        $car = Car::factory()->create();

        $response = $this->deleteJson(route('setups.vehicles.cars.destroy', $car));

        $response->assertNoContent();
        $this->assertSoftDeleted('cars', ['id' => $car->id]);
    });

    it('validates name is required', function () {
        $response = $this->postJson(route('setups.vehicles.cars.store'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    });

    it('can filter by is_active', function () {
        Car::factory()->create(['is_active' => true]);
        Car::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('setups.vehicles.cars.index', ['is_active' => true]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can search cars by name', function () {
        Car::factory()->create(['name' => 'Toyota Hiace']);
        Car::factory()->create(['name' => 'Ford Transit']);

        $response = $this->getJson(route('setups.vehicles.cars.index', ['search' => 'Toyota']));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can list trashed cars', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->getJson(route('setups.vehicles.cars.trashed'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a car', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->patchJson(route('setups.vehicles.cars.restore', $car->id));

        $response->assertOk();
        $this->assertDatabaseHas('cars', ['id' => $car->id, 'deleted_at' => null]);
    });

    it('can force delete a car', function () {
        $car = Car::factory()->create();
        $car->delete();

        $response = $this->deleteJson(route('setups.vehicles.cars.force-delete', $car->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    });
});
