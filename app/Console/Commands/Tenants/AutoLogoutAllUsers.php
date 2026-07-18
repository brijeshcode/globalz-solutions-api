<?php

namespace App\Console\Commands\Tenants;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AutoLogoutAllUsers extends Command
{
    protected $signature = 'users:auto-logout';

    protected $description = 'Revoke all user tokens and mark all active login sessions as logged out across all tenants';

    public function handle(): int
    {
        Tenant::runForEachActive('Auto logout', function (Tenant $tenant) {
            $result = AuthController::autoLogoutAllUsers();

            $this->info("  ✓ {$tenant->tenant_key} — tokens deleted: {$result['tokens_deleted']}, sessions closed: {$result['login_logs_updated']}");

            return $result;
        });

        return self::SUCCESS;
    }
}
