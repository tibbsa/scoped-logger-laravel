<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Tests\Performance;

/**
 * Performance benchmarking utility for scoped-logger-laravel
 *
 * Provides timing and memory measurement capabilities for comparing
 * different logging scenarios and configurations.
 */
class PerformanceBenchmark
{
    /** @var array<string, array{iterations: int, duration: float, memory_start: int, memory_end: int, memory_peak: int}> */
    protected array $results = [];

    protected int $defaultIterations = 100_000;

    protected bool $gcEnabled = true;

    /**
     * Run a benchmark with the specified name and callable
     *
     * @param  string  $name  Unique identifier for this benchmark
     * @param  callable  $callable  The code to benchmark
     * @param  int|null  $iterations  Number of iterations (null = default)
     * @return array{iterations: int, duration: float, memory_start: int, memory_end: int, memory_peak: int, ops_per_second: float, avg_ms_per_op: float}
     */
    public function run(string $name, callable $callable, ?int $iterations = null): array
    {
        $iterations = $iterations ?? $this->defaultIterations;

        // Force garbage collection before benchmarking
        if ($this->gcEnabled) {
            gc_collect_cycles();
            gc_mem_caches();
        }

        $memoryStart = memory_get_usage(true);
        memory_reset_peak_usage();

        $startTime = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $callable($i);
        }

        $endTime = hrtime(true);

        $memoryEnd = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $durationNs = $endTime - $startTime;
        $durationMs = $durationNs / 1_000_000;

        $result = [
            'iterations' => $iterations,
            'duration' => $durationMs,
            'memory_start' => $memoryStart,
            'memory_end' => $memoryEnd,
            'memory_peak' => $memoryPeak,
            'ops_per_second' => $iterations / ($durationMs / 1000),
            'avg_ms_per_op' => $durationMs / $iterations,
        ];

        $this->results[$name] = $result;

        return $result;
    }

    /**
     * Run a warmup phase to ensure JIT compilation and cache warming
     *
     * @param  callable  $callable  The code to warm up
     * @param  int  $iterations  Number of warmup iterations
     */
    public function warmup(callable $callable, int $iterations = 1000): void
    {
        for ($i = 0; $i < $iterations; $i++) {
            $callable($i);
        }

        // Clear memory after warmup
        if ($this->gcEnabled) {
            gc_collect_cycles();
        }
    }

    /**
     * Compare two benchmark results and return the percentage difference
     *
     * @return array{baseline: string, comparison: string, duration_diff_pct: float, ops_per_second_diff_pct: float, memory_diff_bytes: int, faster: string}
     */
    public function compare(string $baselineName, string $comparisonName): array
    {
        if (! isset($this->results[$baselineName])) {
            throw new \InvalidArgumentException("Baseline benchmark '{$baselineName}' not found");
        }
        if (! isset($this->results[$comparisonName])) {
            throw new \InvalidArgumentException("Comparison benchmark '{$comparisonName}' not found");
        }

        $baseline = $this->results[$baselineName];
        $comparison = $this->results[$comparisonName];

        $durationDiffPct = (($comparison['duration'] - $baseline['duration']) / $baseline['duration']) * 100;
        $opsDiffPct = (($comparison['ops_per_second'] - $baseline['ops_per_second']) / $baseline['ops_per_second']) * 100;
        $memoryDiff = $comparison['memory_peak'] - $baseline['memory_peak'];

        return [
            'baseline' => $baselineName,
            'comparison' => $comparisonName,
            'duration_diff_pct' => round($durationDiffPct, 2),
            'ops_per_second_diff_pct' => round($opsDiffPct, 2),
            'memory_diff_bytes' => $memoryDiff,
            'faster' => $durationDiffPct < 0 ? $comparisonName : $baselineName,
        ];
    }

    /**
     * Get all benchmark results
     *
     * @return array<string, array{iterations: int, duration: float, memory_start: int, memory_end: int, memory_peak: int, ops_per_second: float, avg_ms_per_op: float}>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get a specific benchmark result
     *
     * @return array{iterations: int, duration: float, memory_start: int, memory_end: int, memory_peak: int, ops_per_second: float, avg_ms_per_op: float}|null
     */
    public function getResult(string $name): ?array
    {
        return $this->results[$name] ?? null;
    }

    /**
     * Clear all results
     */
    public function clear(): void
    {
        $this->results = [];
    }

    /**
     * Set the default number of iterations
     */
    public function setDefaultIterations(int $iterations): void
    {
        $this->defaultIterations = $iterations;
    }

    /**
     * Get the default number of iterations
     */
    public function getDefaultIterations(): int
    {
        return $this->defaultIterations;
    }

    /**
     * Enable or disable garbage collection during benchmarks
     */
    public function setGcEnabled(bool $enabled): void
    {
        $this->gcEnabled = $enabled;
    }

    /**
     * Format bytes to human-readable string
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = abs($bytes);

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $sign = $bytes < 0 ? '-' : '';

        return $sign.round($value, 2).' '.$units[$unitIndex];
    }

    /**
     * Format duration in milliseconds to human-readable string
     */
    public static function formatDuration(float $milliseconds): string
    {
        if ($milliseconds < 1) {
            return round($milliseconds * 1000, 2).' µs';
        }
        if ($milliseconds < 1000) {
            return round($milliseconds, 2).' ms';
        }

        return round($milliseconds / 1000, 2).' s';
    }

    /**
     * Generate a summary report of all benchmarks
     *
     * @return array<string, array{name: string, duration: string, ops_per_second: string, avg_per_op: string, memory_peak: string}>
     */
    public function generateSummary(): array
    {
        $summary = [];

        foreach ($this->results as $name => $result) {
            $summary[$name] = [
                'name' => $name,
                'duration' => self::formatDuration($result['duration']),
                'ops_per_second' => number_format($result['ops_per_second'], 0),
                'avg_per_op' => self::formatDuration($result['avg_ms_per_op']),
                'memory_peak' => self::formatBytes($result['memory_peak']),
            ];
        }

        return $summary;
    }

    /**
     * Print a formatted table of all benchmark results
     */
    public function printResults(): void
    {
        $summary = $this->generateSummary();

        if (empty($summary)) {
            echo "No benchmark results available.\n";

            return;
        }

        // Calculate column widths
        $nameWidth = max(array_map(fn ($r) => strlen($r['name']), $summary));
        $nameWidth = max($nameWidth, 10);

        echo "\n";
        echo str_repeat('=', 100)."\n";
        echo "PERFORMANCE BENCHMARK RESULTS\n";
        echo str_repeat('=', 100)."\n";
        printf(
            "%-{$nameWidth}s | %12s | %15s | %12s | %12s\n",
            'Benchmark',
            'Duration',
            'Ops/sec',
            'Avg/op',
            'Peak Memory'
        );
        echo str_repeat('-', 100)."\n";

        foreach ($summary as $result) {
            printf(
                "%-{$nameWidth}s | %12s | %15s | %12s | %12s\n",
                $result['name'],
                $result['duration'],
                $result['ops_per_second'],
                $result['avg_per_op'],
                $result['memory_peak']
            );
        }

        echo str_repeat('=', 100)."\n";
    }

    /**
     * Print comparison results between benchmarks
     *
     * @param  array<array{baseline: string, comparison: string}>  $comparisons
     */
    public function printComparisons(array $comparisons): void
    {
        echo "\n";
        echo str_repeat('=', 100)."\n";
        echo "PERFORMANCE COMPARISONS\n";
        echo str_repeat('=', 100)."\n";

        foreach ($comparisons as $comparison) {
            try {
                $result = $this->compare($comparison['baseline'], $comparison['comparison']);

                $overheadSign = $result['duration_diff_pct'] > 0 ? '+' : '';
                $overheadColor = $result['duration_diff_pct'] > 50 ? '⚠️ ' : '';

                printf(
                    "%s%s vs %s: %s%.2f%% duration (%s%.2f%% ops/sec), %s memory\n",
                    $overheadColor,
                    $result['comparison'],
                    $result['baseline'],
                    $overheadSign,
                    $result['duration_diff_pct'],
                    $result['ops_per_second_diff_pct'] > 0 ? '+' : '',
                    $result['ops_per_second_diff_pct'],
                    self::formatBytes($result['memory_diff_bytes'])
                );
            } catch (\InvalidArgumentException $e) {
                echo "Comparison error: {$e->getMessage()}\n";
            }
        }

        echo str_repeat('=', 100)."\n";
    }
}
