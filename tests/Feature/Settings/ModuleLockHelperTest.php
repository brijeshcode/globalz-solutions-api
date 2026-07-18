<?php

use App\Contracts\ModuleLockable;
use App\Helpers\SettingsHelper;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;

function makeLockable(string $key, ?CarbonInterface $date, bool $exempt = false): ModuleLockable
{
    return new class($key, $date, $exempt) implements ModuleLockable {
        public function __construct(
            private string $key,
            private ?CarbonInterface $date,
            private bool $exempt,
        ) {}

        public function moduleLockKey(): string
        {
            return $this->key;
        }

        public function moduleLockDate(): ?CarbonInterface
        {
            return $this->date;
        }

        public function isModuleLockExempt(): bool
        {
            return $this->exempt;
        }
    };
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

it('locks a record older than the configured days for an admin user', function () {
    $this->actingAs($this->admin, 'sanctum');
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $record = makeLockable('sale', now()->subDays(30));

    expect(SettingsHelper::isRecordLocked($record))->toBeTrue();
});

it('does not lock a record within the configured days', function () {
    $this->actingAs($this->admin, 'sanctum');
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $record = makeLockable('sale', now()->subDays(3));

    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('does not lock when the module lock is disabled (0 days)', function () {
    $this->actingAs($this->admin, 'sanctum');

    $record = makeLockable('sale', now()->subDays(300));

    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('does not lock exempt records regardless of age', function () {
    $this->actingAs($this->admin, 'sanctum');
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $record = makeLockable('sale', now()->subDays(300), exempt: true);

    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('bypasses the lock for super admin and developer users', function () {
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);
    $record = makeLockable('sale', now()->subDays(300));

    $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($superAdmin, 'sanctum');
    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();

    $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    $this->actingAs($developer, 'sanctum');
    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('bypasses the lock when no user is authenticated (console and queue jobs)', function () {
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $record = makeLockable('sale', now()->subDays(300));

    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('does not lock a record with a null lock date', function () {
    $this->actingAs($this->admin, 'sanctum');
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $record = makeLockable('sale', null);

    expect(SettingsHelper::isRecordLocked($record))->toBeFalse();
});

it('reads the configured days for a module', function () {
    Setting::set('module_locks', 'purchase', 14, Setting::TYPE_NUMBER);

    expect(SettingsHelper::moduleLockDays('purchase'))->toBe(14)
        ->and(SettingsHelper::moduleLockDays('expense'))->toBe(0);
});
