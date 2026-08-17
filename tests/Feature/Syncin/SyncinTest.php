<?php

use App\Helpers\FeatureHelper;
use App\Helpers\SettingsHelper;
use App\Models\Accounts\IncomeTransaction;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\User;

uses()->group('api', 'syncin');

/**
 * Turn on only the landlord licence for syncin (leaves the tenant setting off).
 */
if (! function_exists('licenseSyncin')) {
    function licenseSyncin($tenant): void
    {
        $feature = Feature::on('mysql')->firstOrCreate(
            ['key' => 'syncin_old_local_system'],
            ['name' => 'Sync-in Flag', 'description' => 'test', 'is_active' => true]
        );

        TenantFeature::on('mysql')->updateOrCreate(
            ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
            ['is_enabled' => true, 'settings' => null]
        );

        TenantFeature::clearCache($tenant->id);
        FeatureHelper::flush();
    }
}

/**
 * Turn syncin fully on: landlord licence AND tenant setting.
 */
if (! function_exists('enableSyncin')) {
    function enableSyncin($tenant): void
    {
        licenseSyncin($tenant);
        SettingsHelper::enableFeature('syncin_old_local_system');
    }
}

beforeEach(function () {
    // UserFactory defaults to super_admin, which passes isAdmin().
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Syncin endpoint', function () {
    it('is blocked when the feature is not licensed', function () {
        $income = IncomeTransaction::factory()->create();

        $response = $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [$income->id],
            'is_synced' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('sync_to_old', ['model_id' => $income->id]);
    });

    it('is blocked when licensed but the tenant setting is off', function () {
        licenseSyncin($this->tenant);
        $income = IncomeTransaction::factory()->create();

        $response = $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [$income->id],
            'is_synced' => true,
        ]);

        $response->assertForbidden();
    });

    it('marks a single record as synced', function () {
        enableSyncin($this->tenant);
        $income = IncomeTransaction::factory()->create();

        $response = $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [$income->id],
            'is_synced' => true,
        ]);

        $response->assertOk();
        expect($response->json('data.updated'))->toBe(1);

        $this->assertDatabaseHas('sync_to_old', [
            'model'     => 'income_transactions',
            'model_id'  => $income->id,
            'is_synced' => true,
        ]);
    });

    it('marks records in bulk and can unset them again', function () {
        enableSyncin($this->tenant);
        $incomes = IncomeTransaction::factory()->count(3)->create();
        $ids = $incomes->pluck('id')->all();

        $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => $ids,
            'is_synced' => true,
        ])->assertOk();

        expect(
            \App\Models\SyncToOld::where('model', 'income_transactions')->where('is_synced', true)->count()
        )->toBe(3);

        // Un-tick the first one — updateOrCreate should flip the existing row, not add a new one.
        $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [$ids[0]],
            'is_synced' => false,
        ])->assertOk();

        $this->assertDatabaseHas('sync_to_old', [
            'model'     => 'income_transactions',
            'model_id'  => $ids[0],
            'is_synced' => false,
        ]);
        expect(\App\Models\SyncToOld::where('model', 'income_transactions')->count())->toBe(3);
    });

    it('is admin only', function () {
        enableSyncin($this->tenant);
        $this->actingAs(User::factory()->salesman()->create(), 'sanctum');
        $income = IncomeTransaction::factory()->create();

        $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [$income->id],
            'is_synced' => true,
        ])->assertForbidden();
    });

    it('rejects an unknown module', function () {
        enableSyncin($this->tenant);

        $this->patchJson(route('syncin.update'), [
            'module'    => 'not_a_module',
            'ids'       => [1],
            'is_synced' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['module']);
    });

    it('rejects when none of the ids exist', function () {
        enableSyncin($this->tenant);

        $this->patchJson(route('syncin.update'), [
            'module'    => 'income_transactions',
            'ids'       => [999999],
            'is_synced' => true,
        ])->assertUnprocessable();
    });
});

describe('Syncin field embedding', function () {
    it('omits is_synced_to_old from list responses when the feature is off', function () {
        IncomeTransaction::factory()->create();

        $response = $this->getJson(route('income-transactions.index'));

        $response->assertOk();
        $first = $response->json('data.0');
        expect($first)->not->toHaveKey('is_synced_to_old');
    });

    it('includes is_synced_to_old in list responses when the feature is on', function () {
        enableSyncin($this->tenant);
        $income = IncomeTransaction::factory()->create();
        \App\Models\SyncToOld::create([
            'model'     => 'income_transactions',
            'model_id'  => $income->id,
            'is_synced' => true,
        ]);

        $response = $this->getJson(route('income-transactions.index'));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $income->id);
        expect($row)->toHaveKey('is_synced_to_old');
        expect($row['is_synced_to_old'])->toBeTrue();
    });
});

describe('Syncin tenant setting', function () {
    it('reports the current setting', function () {
        licenseSyncin($this->tenant);

        $this->getJson(route('settings.syncin.get'))
            ->assertOk()
            ->assertJson(['data' => ['enabled' => false]]);
    });

    it('lets an admin toggle the setting on', function () {
        licenseSyncin($this->tenant);

        $this->putJson(route('settings.syncin.update'), ['enabled' => true])
            ->assertOk()
            ->assertJson(['data' => ['enabled' => true]]);

        expect(SettingsHelper::isFeatureEnabled('syncin_old_local_system'))->toBeTrue();
    });

    it('forbids a non-admin from toggling the setting', function () {
        licenseSyncin($this->tenant);
        $this->actingAs(User::factory()->salesman()->create(), 'sanctum');

        $this->putJson(route('settings.syncin.update'), ['enabled' => true])
            ->assertForbidden();
    });
});
