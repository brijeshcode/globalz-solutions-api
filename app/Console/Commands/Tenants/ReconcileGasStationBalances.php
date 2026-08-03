<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Models\Vehicle\GasStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileGasStationBalances extends Command
{
    protected $signature = 'gas-stations:reconcile-balances';

    protected $description = 'Recalculate and fix gas station balances where stored value differs from payments minus refills';

    public function handle(): int
    {
        Tenant::runForEachActive('Gas station balance reconciliation', function (Tenant $tenant) {
            $fixed = 0;

            GasStation::all()->each(function (GasStation $station) use (&$fixed) {
                $paymentsSum = $station->payments()->sum('amount');
                $refillsSum  = $station->refills()->sum('amount');
                $correct     = bcsub((string) $paymentsSum, (string) $refillsSum, 4);

                if (bccomp((string) $station->balance, $correct, 4) !== 0) {
                    DB::table('gas_stations')
                        ->where('id', $station->id)
                        ->update(['balance' => $correct]);
                    $fixed++;
                }
            });

            $this->info("  ✓ {$tenant->tenant_key} — {$fixed} gas station(s) corrected");

            return ['fixed' => $fixed];
        });

        return self::SUCCESS;
    }
}
