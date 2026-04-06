<?php

use App\Helpers\FeatureHelper;
use App\Models\MirrorLog;
use App\Services\Mirror\DatabaseMirrorService;
use Tests\Feature\Mirror\Concerns\HasMirrorSetup;

uses(HasMirrorSetup::class);

beforeEach(function () {
    $this->setUpMirror();
});

it('triggers a mirror and returns success log', function () {
    $log = MirrorLog::factory()->create();

    $this->mock(DatabaseMirrorService::class, function ($mock) use ($log) {
        $mock->shouldReceive('run')->once()->andReturn($log);
    });

    $this->postJson(route('mirrors.trigger'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'status', 'started_at', 'completed_at', 'duration_seconds', 'remote_host']]);
});

it('returns skipped message when no changes detected', function () {
    $this->mock(DatabaseMirrorService::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andReturn(null);
    });

    $this->postJson(route('mirrors.trigger'))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Mirror skipped — no changes detected since last mirror.']);
});

it('returns failed status when mirror fails', function () {
    $log = MirrorLog::factory()->failed()->create();

    $this->mock(DatabaseMirrorService::class, function ($mock) use ($log) {
        $mock->shouldReceive('run')->once()->andReturn($log);
    });

    $this->postJson(route('mirrors.trigger'))
        ->assertOk()
        ->assertJsonFragment(['status' => MirrorLog::STATUS_FAILED]);
});

it('returns 403 if feature not enabled', function () {
    $this->setUpMirror(featureEnabled: false);

    $this->postJson(route('mirrors.trigger'))
        ->assertForbidden();
});
