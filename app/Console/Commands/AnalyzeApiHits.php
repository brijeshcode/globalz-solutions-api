<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyzeApiHits extends Command
{
    protected $signature = 'api:analyze';

    protected $cleanDays = 365;
    
    protected $description = 'Analyze api-hits.log and generate an API usage report';

    public function handle()
    {
        $logPath = storage_path('logs/api-hits.log');

        if (!file_exists($logPath)) {
            $this->error('No api-hits.log file found.');
            return 1;
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($lines)) {
            $this->error('api-hits.log is empty.');
            return 1;
        }

        $endpoints = [];
        $totalTime = [];
        $maxTime = [];
        $statusCodes = [];
        $monthly = []; // month => endpoint => [hits, totalTime, maxTime]
        $users = []; // "id|name" => [hits, totalTime, endpoints => [endpoint => hits]]

        foreach ($lines as $line) {
            // Extract timestamp
            preg_match('/^\[(\d{4}-\d{2})/', $line, $monthMatch);
            $month = $monthMatch[1] ?? 'unknown';

            if (!preg_match('/"method":"([^"]+)"/', $line, $methodMatch)) continue;
            if (!preg_match('/"url":"([^"]+)"/', $line, $urlMatch)) continue;
            if (!preg_match('/"duration":"([0-9.]+)ms"/', $line, $durationMatch)) continue;
            if (!preg_match('/"status":(\d+)/', $line, $statusMatch)) continue;

            $method = $methodMatch[1];
            $url = $urlMatch[1];
            $duration = (float) $durationMatch[1];
            $status = $statusMatch[1];

            // Extract user info
            preg_match('/"user_id":(\d+|null)/', $line, $userIdMatch);
            preg_match('/"user_name":"([^"]*)"/', $line, $userNameMatch);
            $userId = ($userIdMatch[1] ?? 'null') !== 'null' ? $userIdMatch[1] : null;
            $userName = $userNameMatch[1] ?? null;

            // Normalize URL: replace numeric IDs with {id}
            $normalized = preg_replace('/\/\d+/', '/{id}', $url);
            $key = $method . ' ' . $normalized;

            // Overall stats
            $endpoints[$key] = ($endpoints[$key] ?? 0) + 1;
            $totalTime[$key] = ($totalTime[$key] ?? 0) + $duration;
            $maxTime[$key] = max($maxTime[$key] ?? 0, $duration);
            $statusCodes[$key][$status] = ($statusCodes[$key][$status] ?? 0) + 1;

            // Monthly stats
            if (!isset($monthly[$month][$key])) {
                $monthly[$month][$key] = ['hits' => 0, 'totalTime' => 0, 'maxTime' => 0];
            }
            $monthly[$month][$key]['hits']++;
            $monthly[$month][$key]['totalTime'] += $duration;
            $monthly[$month][$key]['maxTime'] = max($monthly[$month][$key]['maxTime'], $duration);

            // User stats
            $userKey = $userId ? "{$userId}|{$userName}" : 'guest|Guest';
            if (!isset($users[$userKey])) {
                $users[$userKey] = ['hits' => 0, 'totalTime' => 0, 'endpoints' => []];
            }
            $users[$userKey]['hits']++;
            $users[$userKey]['totalTime'] += $duration;
            $users[$userKey]['endpoints'][$key] = ($users[$userKey]['endpoints'][$key] ?? 0) + 1;
        }

        arsort($endpoints);

        // Build console table
        $tableRows = [];
        $rank = 1;
        foreach (array_slice($endpoints, 0, 30, true) as $key => $count) {
            $avgTime = round($totalTime[$key] / $count, 2);
            $max = round($maxTime[$key], 2);
            $statuses = collect($statusCodes[$key])
                ->map(fn ($c, $s) => "{$s}:{$c}")
                ->implode(' ');

            $tableRows[] = [
                $rank++,
                $count,
                $avgTime . 'ms',
                $max . 'ms',
                $statuses,
                $key,
            ];
        }

        $this->table(['#', 'Hits', 'Avg Time', 'Max Time', 'Status Codes', 'Endpoint'], $tableRows);

        // User summary console table
        uasort($users, fn ($a, $b) => $b['hits'] <=> $a['hits']);
        $userRows = [];
        $rank = 1;
        foreach (array_slice($users, 0, 15, true) as $userKey => $data) {
            [$id, $name] = explode('|', $userKey, 2);
            $avgTime = round($data['totalTime'] / $data['hits'], 2);
            arsort($data['endpoints']);
            $topEndpoint = array_key_first($data['endpoints']);
            $userRows[] = [$rank++, $id, $name, $data['hits'], $avgTime . 'ms', $topEndpoint];
        }

        $this->newLine();
        $this->info('User Activity:');
        $this->table(['#', 'User ID', 'Name', 'Hits', 'Avg Time', 'Top Endpoint'], $userRows);

        // Generate markdown report
        $report = "# API Usage Report\n\n";
        $report .= "Generated: " . now()->toDateTimeString() . "\n\n";
        $report .= "Total API hits logged: " . count($lines) . "\n\n";
        $report .= "## Overall\n\n";
        $report .= "| # | Hits | Avg Time | Max Time | Status Codes | Endpoint |\n";
        $report .= "|--:|-----:|---------:|---------:|--------------|----------|\n";

        $rank = 1;
        foreach (array_slice($endpoints, 0, 30, true) as $key => $count) {
            $avgTime = round($totalTime[$key] / $count, 2);
            $max = round($maxTime[$key], 2);
            $statuses = collect($statusCodes[$key])
                ->map(fn ($c, $s) => "{$s}:{$c}")
                ->implode(' ');

            $report .= "| {$rank} | {$count} | {$avgTime}ms | {$max}ms | {$statuses} | `{$key}` |\n";
            $rank++;
        }

        // Slowest endpoints
        $byAvgTime = [];
        foreach ($endpoints as $key => $count) {
            $byAvgTime[$key] = round($totalTime[$key] / $count, 2);
        }
        arsort($byAvgTime);

        $report .= "\n## Slowest Endpoints (by avg response time)\n\n";
        $report .= "| # | Avg Time | Max Time | Hits | Endpoint |\n";
        $report .= "|--:|---------:|---------:|-----:|----------|\n";

        $rank = 1;
        foreach (array_slice($byAvgTime, 0, 10, true) as $key => $avgTime) {
            $count = $endpoints[$key];
            $max = round($maxTime[$key], 2);
            $report .= "| {$rank} | {$avgTime}ms | {$max}ms | {$count} | `{$key}` |\n";
            $rank++;
        }

        // Monthly summary
        ksort($monthly);
        $report .= "\n## Monthly Summary\n\n";

        foreach ($monthly as $month => $endpointData) {
            $totalHits = array_sum(array_column($endpointData, 'hits'));
            $report .= "### {$month} ({$totalHits} total hits)\n\n";
            $report .= "| # | Hits | Avg Time | Max Time | Endpoint |\n";
            $report .= "|--:|-----:|---------:|---------:|----------|\n";

            // Sort by hits descending
            uasort($endpointData, fn ($a, $b) => $b['hits'] <=> $a['hits']);

            $rank = 1;
            foreach (array_slice($endpointData, 0, 20, true) as $key => $data) {
                $avgTime = round($data['totalTime'] / $data['hits'], 2);
                $max = round($data['maxTime'], 2);
                $report .= "| {$rank} | {$data['hits']} | {$avgTime}ms | {$max}ms | `{$key}` |\n";
                $rank++;
            }

            $report .= "\n";
        }

        // User activity summary
        uasort($users, fn ($a, $b) => $b['hits'] <=> $a['hits']);

        $report .= "\n## User Activity\n\n";
        $report .= "| # | User ID | User Name | Hits | Avg Time | Top Endpoint |\n";
        $report .= "|--:|--------:|-----------|-----:|---------:|--------------|\n";

        $rank = 1;
        foreach ($users as $userKey => $data) {
            [$id, $name] = explode('|', $userKey, 2);
            $avgTime = round($data['totalTime'] / $data['hits'], 2);
            arsort($data['endpoints']);
            $topEndpoint = array_key_first($data['endpoints']);
            $topHits = $data['endpoints'][$topEndpoint];
            $report .= "| {$rank} | {$id} | {$name} | {$data['hits']} | {$avgTime}ms | `{$topEndpoint}` ({$topHits}x) |\n";
            $rank++;
        }

        // Per-user endpoint breakdown
        $report .= "\n## User Endpoint Breakdown\n\n";
        foreach (array_slice($users, 0, 10, true) as $userKey => $data) {
            [$id, $name] = explode('|', $userKey, 2);
            $report .= "### {$name} (ID: {$id}) — {$data['hits']} hits\n\n";
            $report .= "| # | Hits | Endpoint |\n";
            $report .= "|--:|-----:|----------|\n";

            arsort($data['endpoints']);
            $r = 1;
            foreach (array_slice($data['endpoints'], 0, 10, true) as $ep => $hits) {
                $report .= "| {$r} | {$hits} | `{$ep}` |\n";
                $r++;
            }
            $report .= "\n";
        }

        $reportPath = storage_path('logs/api-report.md');
        file_put_contents($reportPath, $report);

        $this->newLine();
        $this->info("Report saved to: storage/logs/api-report.md");

        // Clean old log entries
        $this->cleanOldLogs($logPath, $lines);

        return 0;
    }

    protected function cleanOldLogs(string $logPath, array $lines): void
    {
        $cutoff = Carbon::now()->subDays($this->cleanDays)->format('Y-m-d');
        $kept = [];

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $match)) {
                if ($match[1] >= $cutoff) {
                    $kept[] = $line;
                }
            } else {
                $kept[] = $line;
            }
        }

        $removed = count($lines) - count($kept);

        if ($removed > 0) {
            file_put_contents($logPath, implode("\n", $kept) . "\n");
            $this->info("Cleaned {$removed} log entries older than {$this->cleanDays} days.");
        } else {
            $this->info("No log entries older than {$this->cleanDays} days to clean.");
        }
    }
}
