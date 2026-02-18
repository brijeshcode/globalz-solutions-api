<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeQueries extends Command
{
    protected $signature = 'query:analyze';

    protected $description = 'Analyze slow-queries.log and generate a query report';

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

        foreach ($lines as $line) {
            if (!preg_match('/"sql":"([^"]+)"/', $line, $sqlMatch)) {
                continue;
            }
            if (!preg_match('/"time":"([0-9.]+)ms"/', $line, $timeMatch)) {
                continue;
            }

            $sql = $sqlMatch[1];
            // Normalize: replace specific values with ? for grouping
            $normalized = preg_replace('/\bin \([\d, ]+\)/', 'in (?)', $sql);
            $normalized = preg_replace('/= \d+/', '= ?', $normalized);

            $queries[$normalized] = ($queries[$normalized] ?? 0) + 1;
            $totalTime[$normalized] = ($totalTime[$normalized] ?? 0) + (float) $timeMatch[1];
        }

        arsort($queries);

        // Build table for console
        $tableRows = [];
        $rank = 1;
        foreach (array_slice($queries, 0, 25, true) as $sql => $count) {
            $avgTime = round($totalTime[$sql] / $count, 2);
            $tableRows[] = [
                $rank++,
                $count,
                $avgTime . 'ms',
                round($totalTime[$sql], 2) . 'ms',
                strlen($sql) > 80 ? substr($sql, 0, 80) . '...' : $sql,
            ];
        }

        $this->table(['#', 'Count', 'Avg Time', 'Total Time', 'Query'], $tableRows);

        // Generate markdown report
        $report = "# Query Analysis Report\n\n";
        $report .= "Generated: " . now()->toDateTimeString() . "\n\n";
        $report .= "Total queries logged: " . count($lines) . "\n\n";
        $report .= "| # | Count | Avg Time | Total Time | Query |\n";
        $report .= "|--:|------:|---------:|-----------:|-------|\n";

        $rank = 1;
        foreach (array_slice($queries, 0, 25, true) as $sql => $count) {
            $avgTime = round($totalTime[$sql] / $count, 2);
            $total = round($totalTime[$sql], 2);
            $report .= "| {$rank} | {$count} | {$avgTime}ms | {$total}ms | `" . str_replace('|', '\\|', $sql) . "` |\n";
            $rank++;
        }

        $reportPath = storage_path('logs/query-report.md');
        file_put_contents($reportPath, $report);

        $this->newLine();
        $this->info("Report saved to: storage/logs/query-report.md");

        return 0;
    }
}
