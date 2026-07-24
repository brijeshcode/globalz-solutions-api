<?php

use App\Models\Employees\Employee;
use App\Models\User;

it('rejects non-admins on the admin monthly commission endpoint', function () {
    $employee = Employee::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');

    $this->getJson("/api/employee/business/commission?employee_id={$employee->id}&month=6&year=2026")
        ->assertStatus(403);
});

it('returns commission data for an admin', function () {
    $employee = Employee::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');

    $this->getJson("/api/employee/business/commission?employee_id={$employee->id}&month=6&year=2026")
        ->assertOk()
        ->assertJsonPath('data.total_commission', 0);   // no target assigned => zero
});

it('accepts detailed=true from the query string and returns the daily breakdown', function () {
    $employee = Employee::factory()->create();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');

    $this->getJson("/api/employee/business/commission?employee_id={$employee->id}&month=7&year=2026&detailed=true")
        ->assertOk()
        ->assertJsonStructure(['data' => ['daily_business']]);
});
