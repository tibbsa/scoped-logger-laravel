<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Commands;

use Illuminate\Console\Command;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Support\PatternMatcher;

class TestScopeCommand extends Command
{
    protected $signature = 'scoped-logger:test
                            {scope : The scope identifier to test}
                            {--level=debug : Test log level to check}';

    protected $description = 'Test what log level applies for a given scope';

    public function handle(): int
    {
        /** @var string $scope */
        $scope = $this->argument('scope');
        $testLevel = $this->option('level');
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);

        if (! $config->isEnabled()) {
            $this->warn('Scoped logger is currently disabled.');

            return self::FAILURE;
        }

        $this->info("Testing scope: {$scope}");
        $this->newLine();

        // Find configured level
        $configuredLevel = $this->getConfiguredLevel($scope, $config);
        $matchedPattern = $this->getMatchedPattern($scope, $config);

        // Display results
        if ($matchedPattern !== null && $matchedPattern !== $scope) {
            $this->line("  <fg=yellow>Matched Pattern:</> {$matchedPattern}");
        }

        if ($configuredLevel === false) {
            $this->line('  <fg=red>Configured Level:</> SUPPRESSED');
            $this->newLine();
            $this->error('All logs from this scope will be suppressed.');

            return self::SUCCESS;
        }

        if ($configuredLevel === null) {
            $configuredLevel = $config->defaultLevel();
            $this->line("  <fg=yellow>Configured Level:</> {$configuredLevel} <fg=gray>(default)</>  ");
        } else {
            $this->line("  <fg=green>Configured Level:</> {$configuredLevel}");
        }

        // Test if the specified test level would log
        $this->newLine();
        $testLevelString = is_string($testLevel) ? $testLevel : 'debug';
        $wouldLog = $this->wouldLog($testLevelString, $configuredLevel);

        if ($wouldLog) {
            $this->line("  <fg=green>✓</> Log::{$testLevelString}() <fg=green>WILL BE LOGGED</>");
        } else {
            $this->line("  <fg=red>✗</> Log::{$testLevelString}() <fg=red>WILL BE DROPPED</>");
        }

        // Show all log levels
        $this->newLine();
        $this->line('<fg=gray>Log Level Behavior:</>');

        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        foreach ($levels as $level) {
            $logs = $this->wouldLog($level, $configuredLevel);
            $icon = $logs ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $status = $logs ? '<fg=green>logs</>' : '<fg=gray>drops</>';
            $this->line("  {$icon} {$level} → {$status}");
        }

        return self::SUCCESS;
    }

    protected function getConfiguredLevel(string $scope, Configuration $config): string|false|null
    {
        $scopes = $config->scopes();

        // Check exact match
        if (isset($scopes[$scope])) {
            $level = $scopes[$scope];

            // Handle closures - evaluate them
            if ($level instanceof \Closure) {
                $evaluated = $level();

                return is_string($evaluated) || $evaluated === false ? $evaluated : null;
            }

            return $level;
        }

        // Check pattern match
        if (! empty($scopes)) {
            $matcher = new PatternMatcher($scopes);
            $matchedPattern = $matcher->findMatch($scope);

            if ($matchedPattern !== null && isset($scopes[$matchedPattern])) {
                $level = $scopes[$matchedPattern];

                // Handle closures - evaluate them
                if ($level instanceof \Closure) {
                    $evaluated = $level();

                    return is_string($evaluated) || $evaluated === false ? $evaluated : null;
                }

                return $level;
            }
        }

        return null;
    }

    protected function getMatchedPattern(string $scope, Configuration $config): ?string
    {
        $scopes = $config->scopes();

        // Check exact match
        if (isset($scopes[$scope])) {
            return $scope;
        }

        // Check pattern match
        if (! empty($scopes)) {
            $matcher = new PatternMatcher($scopes);

            return $matcher->findMatch($scope);
        }

        return null;
    }

    protected function wouldLog(string $logLevel, string|false $configuredLevel): bool
    {
        if ($configuredLevel === false) {
            return false;
        }

        $levels = [
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7,
        ];

        $logLevelValue = $levels[$logLevel] ?? 0;
        $configuredLevelValue = $levels[$configuredLevel] ?? 1;

        return $logLevelValue >= $configuredLevelValue;
    }
}
