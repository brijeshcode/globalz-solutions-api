<?php

use App\Models\User;

uses()->group('api', 'setup', 'vehicles', 'vehicles.gas-stations');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});
