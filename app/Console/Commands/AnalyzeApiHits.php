<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeApiHits extends Command
{
    protected $signature = 'api:analyze';

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

        $reportPath = storage_path('logs/api-report.md');
        file_put_contents($reportPath, $report);

        $this->newLine();
        $this->info("Report saved to: storage/logs/api-report.md");

        return 0;
    }
}
