<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantSetupController extends Controller
{
    /**
     * Configure a tenant in a single step.
     *
     * Writes all settings into the tenant's own database.
     * Required: currency settings + at least 1 super_admin and 1 developer user.
     *
     * POST /tenants/{tenant}/setup
     */
    public function setup(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            // Company settings (group: company)
            'company'                  => 'nullable|array',
            'company.name'             => 'nullable|string|max:255',
            'company.address'          => 'nullable|string|max:500',
            'company.phone'            => 'nullable|string|max:50',
            'company.email'            => 'nullable|email|max:255',
            'company.website'          => 'nullable|url|max:255',
            'company.tax_number'       => 'nullable|string|max:100',

            // Tenant branding (group: tenant_details)
            'tenant_details'                   => 'nullable|array',
            'tenant_details.company_name'      => 'nullable|string|max:255',
            'tenant_details.tagline'           => 'nullable|string|max:255',
            'tenant_details.description'       => 'nullable|string|max:500',
            'tenant_details.primary_color'     => 'nullable|string|max:50',
            'tenant_details.secondary_color'   => 'nullable|string|max:50',
            'tenant_details.contact_email'     => 'nullable|email|max:255',
            'tenant_details.contact_phone'     => 'nullable|string|max:50',

            // Currency settings (group: currency)
            'currency'                         => 'required|array',
            'currency.local_currency'          => 'required|string|size:3',
            'currency.system_currency_mode'    => 'required|string|in:single,multi',

            // Required system users
            'users'                => 'required|array|min:2',
            'users.*.name'         => 'required|string|max:255',
            'users.*.email'        => 'required|email|max:255',
            'users.*.password'     => 'required|string|min:6',
            'users.*.role'         => ['required', 'string', Rule::in(User::getRoles())],
        ]);

        // Ensure required roles are present in the users array
        $roles = collect($validated['users'])->pluck('role');

        if (!$roles->contains(User::ROLE_SUPER_ADMIN)) {
            return ApiResponse::failValidation(['users' => 'At least one user with role super_admin is required.']);
        }

        if (!$roles->contains(User::ROLE_DEVELOPER)) {
            return ApiResponse::failValidation(['users' => 'At least one user with role developer is required.']);
        }

        try {
            $tenant->execute(function () use ($validated) {
                DB::transaction(function () use ($validated) {
                    // Company settings
                    if (!empty($validated['company'])) {
                        foreach ($validated['company'] as $key => $value) {
                            Setting::set('company', $key, $value);
                        }
                    }

                    // Tenant branding
                    if (!empty($validated['tenant_details'])) {
                        foreach ($validated['tenant_details'] as $key => $value) {
                            Setting::set('tenant_details', $key, $value);
                        }
                    }

                    // Currency settings
                    Setting::set('currency', 'local_currency', $validated['currency']['local_currency']);

                    // Seed USD as the default base currency
                    TenantCurrencyController::seedUsdIfMissing();

                    // Required users
                    foreach ($validated['users'] as $userData) {
                        User::updateOrCreate(
                            ['email' => $userData['email']],
                            [
                                'name'       => $userData['name'],
                                'password'   => bcrypt($userData['password']),
                                'role'       => $userData['role'],
                                'is_active'  => true,
                                'created_by' => 1,
                                'updated_by' => 1,
                            ]
                        );
                    }
                });
            });

            // Sync multi_currency feature flag based on chosen mode
            $multiCurrencyFeature = Feature::where('key', 'multi_currency')->first();
            if ($multiCurrencyFeature) {
                $isMulti = ($validated['currency']['system_currency_mode'] === 'multi');
                TenantFeature::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_id' => $multiCurrencyFeature->id],
                    ['is_enabled' => $isMulti]
                );
                TenantFeature::clearCache($tenant->id);
            }

            return ApiResponse::store('Tenant setup completed successfully', [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);

        } catch (\Exception $e) {
            return ApiResponse::serverError('Setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Check whether a tenant has the minimum required configuration to operate.
     *
     * GET /tenants/{tenant}/readiness
     */
    public function readiness(Tenant $tenant): JsonResponse
    {
        $checks = $tenant->execute(fn() => [
            'company'        => $this->checkCompanySettings(),
            'tenant_details' => $this->checkTenantDetails(),
            'currency'       => $this->checkCurrencySettings(),
            'users'          => $this->checkRequiredUsers(),
        ]);

        $ready = collect($checks)->every(fn($check) => $check['passed']);

        return ApiResponse::show('Tenant readiness check', [
            'ready'  => $ready,
            'checks' => $checks,
        ]);
    }

    // ─── Private checks (called from within tenant context) ───────────────────

    private function checkCompanySettings(): array
    {
        $name   = Setting::get('company', 'name');
        $passed = !empty($name);

        return [
            'passed'  => $passed,
            'missing' => $passed ? [] : ['company name not set'],
        ];
    }

    private function checkTenantDetails(): array
    {
        $name   = Setting::get('tenant_details', 'company_name');
        $passed = !empty($name);

        return [
            'passed'  => $passed,
            'missing' => $passed ? [] : ['tenant_details company_name not set'],
        ];
    }

    private function checkCurrencySettings(): array
    {
        $missing = [];

        if (empty(Setting::get('currency', 'local_currency'))) {
            $missing[] = 'local_currency not set';
        }

        return ['passed' => empty($missing), 'missing' => $missing];
    }

    private function checkRequiredUsers(): array
    {
        $missing = [];

        if (!User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->exists()) {
            $missing[] = 'no active super_admin user';
        }

        if (!User::where('role', User::ROLE_DEVELOPER)->where('is_active', true)->exists()) {
            $missing[] = 'no active developer user';
        }

        return ['passed' => empty($missing), 'missing' => $missing];
    }
}
