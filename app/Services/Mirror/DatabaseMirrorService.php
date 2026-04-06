<?php

namespace App\Services\Mirror;

use App\Models\MirrorLog;
use App\Models\Setting;
use App\Models\Tenant;
use Carbon\Carbon;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\DB;

class DatabaseMirrorService
{
    /**
     * Validate that a host is not a private or internal IP address (SSRF protection).
     *
     * @throws \InvalidArgumentException
     */
    public function validateHost(string $host): void
    {
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new \InvalidArgumentException('Private or internal IP addresses are not allowed as mirror host.');
            }
        }
    }

    /**
     * Run a full mirror cycle for the given tenant.
     * Returns null if skipped (no changes detected), MirrorLog otherwise.
     */
    public function run(Tenant $tenant, ?int $triggeredBy = null): ?MirrorLog
    {
        $host           = Setting::get('mirror', 'host');
        $port           = (int) Setting::get('mirror', 'port', 3306, false, Setting::TYPE_NUMBER);
        $database       = Setting::get('mirror', 'database');
        $username       = Setting::get('mirror', 'username');
        $password       = $this->getMirrorPassword();
        $lastMirroredAt = Setting::get('mirror', 'last_mirrored_at');

        $this->validateHost($host);

        // Skip if nothing changed since last mirror
        if ($lastMirroredAt && !$this->hasChangedSince($tenant, $lastMirroredAt)) {
            return null;
        }

        $log = MirrorLog::create([
            'status'       => MirrorLog::STATUS_PENDING,
            'triggered_by' => $triggeredBy,
            'started_at'   => now(),
            'remote_host'  => $host,
        ]);

        $log->update(['status' => MirrorLog::STATUS_RUNNING]);

        $startTime   = microtime(true);
        $tempSqlPath = storage_path('app/backups/_mirror_tmp_' . $log->id . '.sql');

        try {
            $this->ensureTempDirectory();
            $dbConfig = $tenant->getDatabaseConfig();
            $this->dumpDatabase($dbConfig, $tempSqlPath);
            $this->restoreToRemote($host, $port, $database, $username, $password, $tempSqlPath);

            $log->update([
                'status'           => MirrorLog::STATUS_SUCCESS,
                'completed_at'     => now(),
                'duration_seconds' => (int) round(microtime(true) - $startTime),
            ]);

            Setting::set('mirror', 'last_mirrored_at', now()->toIso8601String());

            $this->pruneOldLogs();
        } catch (\Throwable $e) {
            $log->update([
                'status'           => MirrorLog::STATUS_FAILED,
                'completed_at'     => now(),
                'duration_seconds' => (int) round(microtime(true) - $startTime),
                'error_message'    => $e->getMessage(),
            ]);
        } finally {
            if (file_exists($tempSqlPath)) {
                unlink($tempSqlPath);
            }
        }

        return $log->fresh();
    }

    /**
     * Check information_schema to see if any table was updated after $since.
     */
    public function hasChangedSince(Tenant $tenant, string $since): bool
    {
        $dbConfig  = $tenant->getDatabaseConfig();
        $dbName    = $dbConfig['database'];
        $sinceDate = Carbon::parse($since)->format('Y-m-d H:i:s');

        $result = DB::connection('mysql')->selectOne(
            "SELECT MAX(UPDATE_TIME) as last_changed
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND UPDATE_TIME IS NOT NULL",
            [$dbName]
        );

        if (!$result || !$result->last_changed) {
            return true;
        }

        return $result->last_changed > $sinceDate;
    }

    /**
     * Dump tenant DB to a plain .sql temp file (no compression — needed for PDO replay).
     */
    protected function dumpDatabase(array $dbConfig, string $outputPath): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $dbConfig['host'],
            $dbConfig['port'] ?? 3306,
            $dbConfig['database']
        );

        $dump = new Mysqldump($dsn, $dbConfig['username'], $dbConfig['password'], [
            'compress'           => Mysqldump::NONE,
            'single-transaction' => true,
            'add-drop-table'     => true,
            'skip-triggers'      => false,
            'add-locks'          => true,
        ]);

        $dump->start($outputPath);
    }

    /**
     * Replay a .sql dump file on the remote MySQL server via PDO, statement by statement.
     */
    protected function restoreToRemote(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        string $sqlPath
    ): void {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT            => 30,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);

        $handle    = fopen($sqlPath, 'r');
        $statement = '';

        while (!feof($handle)) {
            $line = fgets($handle);

            if (str_starts_with(trim($line), '--') || trim($line) === '') {
                continue;
            }

            $statement .= $line;

            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($statement);
                $statement = '';
            }
        }

        fclose($handle);
    }

    /**
     * Get the decrypted mirror password from Setting.
     */
    protected function getMirrorPassword(): string
    {
        $setting = Setting::where('group_name', 'mirror')
            ->where('key_name', 'password')
            ->first();

        return $setting ? $setting->getCastValue() : '';
    }

    /**
     * Delete oldest MirrorLog records beyond store_limit.
     */
    protected function pruneOldLogs(): void
    {
        $limit = (int) Setting::get('mirror', 'store_limit', 1000, false, Setting::TYPE_NUMBER);
        $count = MirrorLog::count();

        if ($count > $limit) {
            MirrorLog::orderBy('id')
                ->limit($count - $limit)
                ->get()
                ->each(fn($log) => $log->delete());
        }
    }

    protected function ensureTempDirectory(): void
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);

        $ip     = ip2long($ip);
        $subnet = ip2long($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        $mask = $bits == 0 ? 0 : (~0 << (32 - (int) $bits));

        return ($ip & $mask) === ($subnet & $mask);
    }
}
