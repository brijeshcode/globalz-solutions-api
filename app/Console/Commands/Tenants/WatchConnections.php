<?php

namespace App\Console\Commands\Tenants;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Live MySQL connection monitor for real-world testing of the Hostinger
 * "20 new connections/sec" limit.
 *
 * Unlike db:measure-churn (which simulates calls sequentially in one process),
 * this watches the REAL server while YOU drive it from the browser. Start this,
 * then open an actual page in the frontend — it reports how many new connections
 * that page opened and the peak concurrency, going through the real web stack
 * (PHP-FPM workers, concurrent requests, whatever number of APIs the page fires).
 *
 * IMPORTANT: run this against your real local web server (Laragon / nginx + php-fpm),
 * NOT `php artisan serve` — the built-in dev server handles requests one-at-a-time,
 * so it can't show the concurrent multi-worker bursts that trip the production limit.
 *
 * Usage:
 *   php artisan db:watch-connections --seconds=30
 *   (then open the page you want to test in the browser within that window)
 */
class WatchConnections extends Command
{
    protected $signature = 'db:watch-connections
        {--seconds=30 : How long to watch before printing the summary}
        {--interval=250 : Poll interval in milliseconds}';

    protected $description = 'Live-watch new MySQL connections while you open a real page in the browser';

    public function handle(): int
    {
        $seconds  = max(1, (int) $this->option('seconds'));
        $interval = max(50, (int) $this->option('interval'));

        $baselineConn = $this->status('Connections');
        $peakThreads  = $this->status('Threads_connected');
        $startThreads = $peakThreads;

        $this->info("Watching for {$seconds}s — open the page you want to test now.");
        $this->line('Baseline connections counter: ' . $baselineConn . ' | threads open: ' . $startThreads);
        $this->line(str_repeat('-', 60));

        $start    = microtime(true);
        $lastConn = $baselineConn;
        $buckets  = []; // new connections per whole-second bucket → peak/sec

        while ((microtime(true) - $start) < $seconds) {
            usleep($interval * 1000);

            $elapsedSecs = microtime(true) - $start;
            $conn    = $this->status('Connections');
            $threads = $this->status('Threads_connected');
            $peakThreads = max($peakThreads, $threads);

            if ($conn !== $lastConn) {
                $justOpened = $conn - $lastConn;
                $bucket = (int) $elapsedSecs;
                $buckets[$bucket] = ($buckets[$bucket] ?? 0) + $justOpened;

                $newSinceStart = $conn - $baselineConn;
                $this->line(sprintf(
                    '[+%ss] opened %d new (total %d) | this second: %d/s | threads: %d (peak %d)',
                    number_format($elapsedSecs, 1),
                    $justOpened,
                    $newSinceStart,
                    $buckets[$bucket],
                    $threads,
                    $peakThreads
                ));
                $lastConn = $conn;
            }
        }

        $totalNew = $this->status('Connections') - $baselineConn;
        $peakPerSecond = $buckets ? max($buckets) : 0;
        $overLimit = $peakPerSecond >= 20;

        $this->line(str_repeat('-', 60));
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total NEW connections during window', (string) $totalNew],
                ['PEAK new connections in one second', $peakPerSecond . ($overLimit ? '  <-- OVER 20/s CAP' : '  (under 20/s cap)')],
                ['Peak concurrent connections (Threads_connected)', (string) $peakThreads],
                ['Watched for', $seconds . 's'],
            ]
        );

        $this->newLine();
        $this->line('<comment>Read it like this:</comment>');
        $this->line('  "Total NEW connections" ÷ number of pages you opened = new connections per page load.');
        $this->line('  If a single page load opens < ~15, you have headroom under the 20/sec cap.');
        $this->line('  Run once with DB_PERSISTENT off and once with it on, opening the SAME page, to compare.');

        return self::SUCCESS;
    }

    /**
     * Read a MySQL global status counter. Uses the landlord connection; this monitor
     * connection is opened once and reused, so it does not distort the count it reads.
     */
    protected function status(string $name): int
    {
        // SHOW statements don't support bound parameters in MariaDB/MySQL. $name is a
        // fixed internal constant (never user input), so inlining it is safe here.
        $row = DB::connection('mysql')->selectOne("SHOW GLOBAL STATUS LIKE '{$name}'");

        return (int) ($row->Value ?? 0);
    }
}
