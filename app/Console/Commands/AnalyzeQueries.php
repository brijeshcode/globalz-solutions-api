<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyzeQueries extends Command
{
    protected $signature = 'query:analyze';

    protected $cleanDays = 365;

    protected $description = 'Analyze slow-queries.log and generate a query report';

    /**
     * Known issue patterns: regex => [issue, fix]
     */
    private function getIssuePatterns(): array
    {
        return [
            '/select \* from `cache` where `key`/' => [
                'Cache using database driver',
                'Switch CACHE_STORE to file or redis in .env',
            ],
            '/insert into `cache`/' => [
                'Cache writes hitting database',
                'Switch CACHE_STORE to file or redis in .env',
            ],
            '/select \* from `settings` where `group_name`/' => [
                'Settings fetched individually (one query per setting)',
                'Bulk load all settings at once and cache in memory',
            ],
            '/information_schema\.tables.*settings/' => [
                'Repeated table existence check for settings',
                'Cache this check or configure settings package to skip it',
            ],
            '/select count\(\*\).*`parent_id` = \?/' => [
                'N+1: Counting children per record in a loop',
                'Use withCount(\'children\') on the parent query',
            ],
            '/select \* from `sessions`/' => [
                'Session reads from database',
                'Consider switching SESSION_DRIVER to file or redis',
            ],
            '/insert into `sessions`/' => [
                'Session writes to database',
                'Consider switching SESSION_DRIVER to file or redis',
            ],
            '/select exists\(select \* from `documents`/' => [
                'N+1: Checking document existence per record in a loop',
                'Use withExists(\'documents\') or eager load',
            ],
            '/select \* from `documents` where.*`documentable_type`.*`documentable_id` = \?/' => [
                'N+1: Loading latest document per record in a loop',
                'Eager load documents with the parent query',
            ],
            '/select sum\(.*\) from `sales` where.*subquery|select `customers`\.\*.*select sum/' => [
                'Correlated subquery on sales for each customer row',
                'Use a JOIN with GROUP BY or withSum() instead of subquery',
            ],
        ];
    }

    private function detectIssue(string $sql, int $count): array
    {
        foreach ($this->getIssuePatterns() as $pattern => [$issue, $fix]) {
            if (preg_match($pattern, $sql)) {
                return [$issue, $fix];
            }
        }

        if ($count > 20) {
            return ['High frequency query — possible N+1 or loop', 'Check if this runs inside a loop; use eager loading'];
        }

        return ['-', '-'];
    }

    private function normalizeQuery(string $sql): string
    {
        $normalized = preg_replace('/\bin \([\d, ]+\)/', 'in (?)', $sql);
        $normalized = preg_replace('/= \d+/', '= ?', $normalized);
        return $normalized;
    }

    public function handle()
    {
        $logPath = storage_path('logs/slow-queries.log');

        if (!file_exists($logPath)) {
            $this->error('No slow-queries.log file found.');
            return 1;
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($lines)) {
            $this->error('slow-queries.log is empty.');
            return 1;
        }

        $queries = [];
        $totalTime = [];
        $monthly = []; // month => query => [count, totalTime, maxTime]

        foreach ($lines as $line) {
            // Extract timestamp
            preg_match('/^\[(\d{4}-\d{2})/', $line, $monthMatch);
            $month = $monthMatch[1] ?? 'unknown';

            if (!preg_match('/"sql":"([^"]+)"/', $line, $sqlMatch)) continue;
            if (!preg_match('/"time":"([0-9.]+)ms"/', $line, $timeMatch)) continue;

            $normalized = $this->normalizeQuery($sqlMatch[1]);
            $time = (float) $timeMatch[1];

            // Overall stats
            $queries[$normalized] = ($queries[$normalized] ?? 0) + 1;
            $totalTime[$normalized] = ($totalTime[$normalized] ?? 0) + $time;

            // Monthly stats
            if (!isset($monthly[$month][$normalized])) {
                $monthly[$month][$normalized] = ['count' => 0, 'totalTime' => 0, 'maxTime' => 0];
            }
            $monthly[$month][$normalized]['count']++;
            $monthly[$month][$normalized]['totalTime'] += $time;
            $monthly[$month][$normalized]['maxTime'] = max($monthly[$month][$normalized]['maxTime'], $time);
        }

        arsort($queries);

        // Build table for console
        $tableRows = [];
        $rank = 1;
        foreach (array_slice($queries, 0, 25, true) as $sql => $count) {
            $avgTime = round($totalTime[$sql] / $count, 2);
            [$issue] = $this->detectIssue($sql, $count);
            $tableRows[] = [
                $rank++,
                $count,
                $avgTime . 'ms',
                round($totalTime[$sql], 2) . 'ms',
                $issue,
                strlen($sql) > 60 ? substr($sql, 0, 60) . '...' : $sql,
            ];
        }

        $this->table(['#', 'Count', 'Avg Time', 'Total Time', 'Issue', 'Query'], $tableRows);

        // Generate markdown report
        $report = "# Query Analysis Report\n\n";
        $report .= "Generated: " . now()->toDateTimeString() . "\n\n";
        $report .= "Total queries logged: " . count($lines) . "\n\n";
        $report .= "## Query Table\n\n";
        $report .= "| # | Count | Avg Time | Total Time | Issue | Query |\n";
        $report .= "|--:|------:|---------:|-----------:|-------|-------|\n";

        $issues = [];
        $rank = 1;
        foreach (array_slice($queries, 0, 25, true) as $sql => $count) {
            $avgTime = round($totalTime[$sql] / $count, 2);
            $total = round($totalTime[$sql], 2);
            [$issue, $fix] = $this->detectIssue($sql, $count);
            $escapedSql = str_replace('|', '\\|', $sql);
            $report .= "| {$rank} | {$count} | {$avgTime}ms | {$total}ms | {$issue} | `{$escapedSql}` |\n";

            if ($issue !== '-') {
                $issues[] = [
                    'rank' => $rank,
                    'count' => $count,
                    'issue' => $issue,
                    'fix' => $fix,
                    'sql' => $sql,
                ];
            }
            $rank++;
        }

        if (!empty($issues)) {
            $report .= "\n## Issues & Fixes\n\n";
            foreach ($issues as $item) {
                $report .= "### #{$item['rank']} — {$item['issue']} ({$item['count']}x)\n";
                $report .= "- **Fix:** {$item['fix']}\n";
                $report .= "- **Query:** `{$item['sql']}`\n\n";
            }
        }

        // Monthly summary
        ksort($monthly);
        $report .= "\n## Monthly Summary\n\n";

        foreach ($monthly as $month => $queryData) {
            $totalCount = array_sum(array_column($queryData, 'count'));
            $totalMs = round(array_sum(array_column($queryData, 'totalTime')), 2);
            $report .= "### {$month} ({$totalCount} queries, {$totalMs}ms total)\n\n";
            $report .= "| # | Count | Avg Time | Max Time | Issue | Query |\n";
            $report .= "|--:|------:|---------:|---------:|-------|-------|\n";

            // Sort by count descending
            uasort($queryData, fn ($a, $b) => $b['count'] <=> $a['count']);

            $rank = 1;
            foreach (array_slice($queryData, 0, 15, true) as $sql => $data) {
                $avgTime = round($data['totalTime'] / $data['count'], 2);
                $max = round($data['maxTime'], 2);
                [$issue] = $this->detectIssue($sql, $data['count']);
                $escapedSql = str_replace('|', '\\|', $sql);
                $shortSql = strlen($escapedSql) > 80 ? substr($escapedSql, 0, 80) . '...' : $escapedSql;
                $report .= "| {$rank} | {$data['count']} | {$avgTime}ms | {$max}ms | {$issue} | `{$shortSql}` |\n";
                $rank++;
            }

            $report .= "\n";
        }

        $reportPath = storage_path('logs/query-report.md');
        file_put_contents($reportPath, $report);

        $this->newLine();
        $this->info("Report saved to: storage/logs/query-report.md");

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
