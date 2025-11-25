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
                            {--level=debug : Test log level to check}
                            {--channel= : Test against a specific channel}
                            {--all-channels : Test against all channels with overrides}';

    protected $description = 'Test what log level applies for a given scope';

    public function handle(): int
    {
        /** @var string $scope */
        $scope = $this->argument('scope');
        $testLevel = $this->option('level');
        $channelOption = $this->option('channel');
        $allChannels = $this->option('all-channels');
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);

        if (! $config->isEnabled()) {
            $this->warn('Scoped logger is currently disabled.');

            return self::FAILURE;
        }

        $testLevelString = is_string($testLevel) ? $testLevel : 'debug';

        // Test all channels if requested
        if ($allChannels) {
            return $this->testAllChannels($scope, $testLevelString, $config);
        }

        // Test specific channel if requested
        if (is_string($channelOption) && $channelOption !== '') {
            return $this->testScopeForChannel($scope, $testLevelString, $channelOption, $config);
        }

        // Default: test global scope
        return $this->testScopeForChannel($scope, $testLevelString, null, $config);
    }

    /**
     * Test scope against all channels that have overrides.
     */
    protected function testAllChannels(string $scope, string $testLevel, Configuration $config): int
    {
        $channelScopes = $config->channelScopes();

        $this->info("Testing scope: {$scope}");
        $this->line("<fg=gray>Test level:</> {$testLevel}");
        $this->newLine();

        // First show global result
        $this->line('<fg=cyan>Global (no channel):</>');
        $this->displayScopeTest($scope, $testLevel, null, $config, '  ');

        // Then show each channel with overrides
        if (! empty($channelScopes)) {
            foreach (array_keys($channelScopes) as $channel) {
                $this->newLine();
                $this->line("<fg=cyan>Channel: {$channel}</>");
                $this->displayScopeTest($scope, $testLevel, $channel, $config, '  ');
            }
        }

        // Summary table
        $this->newLine();
        $this->displayComparisonTable($scope, $testLevel, $config, $channelScopes);

        return self::SUCCESS;
    }

    /**
     * Test scope for a specific channel (or global if null).
     */
    protected function testScopeForChannel(string $scope, string $testLevel, ?string $channel, Configuration $config): int
    {
        if ($channel !== null) {
            $this->info("Testing scope: {$scope}");
            $this->line("<fg=gray>Channel:</> {$channel}");
        } else {
            $this->info("Testing scope: {$scope}");
        }
        $this->newLine();

        $this->displayScopeTest($scope, $testLevel, $channel, $config, '');

        return self::SUCCESS;
    }

    /**
     * Display scope test results.
     */
    protected function displayScopeTest(
        string $scope,
        string $testLevel,
        ?string $channel,
        Configuration $config,
        string $indent
    ): void {
        // Get the effective scopes for this channel
        $effectiveScopes = $this->getEffectiveScopes($config, $channel);

        // Find configured level
        $configuredLevel = $this->getConfiguredLevel($scope, $effectiveScopes);
        $matchedPattern = $this->getMatchedPattern($scope, $effectiveScopes);

        // Check if this is a channel override
        $isChannelOverride = false;
        if ($channel !== null) {
            $channelSpecificScopes = $config->scopesForChannel($channel);
            $isChannelOverride = isset($channelSpecificScopes[$scope]) ||
                ($matchedPattern !== null && isset($channelSpecificScopes[$matchedPattern]));
        }

        // Display results
        if ($matchedPattern !== null && $matchedPattern !== $scope) {
            $this->line("{$indent}<fg=yellow>Matched Pattern:</> {$matchedPattern}");
        }

        if ($configuredLevel === false) {
            $sourceInfo = $isChannelOverride ? ' <fg=cyan>(channel override)</>' : '';
            $this->line("{$indent}<fg=red>Configured Level:</> SUPPRESSED{$sourceInfo}");
            $this->line("{$indent}<fg=red>All logs from this scope will be suppressed.</>");

            return;
        }

        if ($configuredLevel === null) {
            $configuredLevel = $config->defaultLevel();
            $this->line("{$indent}<fg=yellow>Configured Level:</> {$configuredLevel} <fg=gray>(default)</>");
        } else {
            $sourceInfo = $isChannelOverride ? ' <fg=cyan>(channel override)</>' : '';
            $this->line("{$indent}<fg=green>Configured Level:</> {$configuredLevel}{$sourceInfo}");
        }

        // Test if the specified test level would log
        $wouldLog = $this->wouldLog($testLevel, $configuredLevel);

        if ($wouldLog) {
            $this->line("{$indent}<fg=green>✓</> Log::{$testLevel}() <fg=green>WILL BE LOGGED</>");
        } else {
            $this->line("{$indent}<fg=red>✗</> Log::{$testLevel}() <fg=red>WILL BE DROPPED</>");
        }

        // Show all log levels
        $this->newLine();
        $this->line("{$indent}<fg=gray>Log Level Behavior:</>");

        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        foreach ($levels as $level) {
            $logs = $this->wouldLog($level, $configuredLevel);
            $icon = $logs ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $status = $logs ? '<fg=green>logs</>' : '<fg=gray>drops</>';
            $this->line("{$indent}  {$icon} {$level} → {$status}");
        }
    }

    /**
     * Display a comparison table across all channels.
     *
     * @param  array<string, array<string, string|false|\Closure>>  $channelScopes
     */
    protected function displayComparisonTable(
        string $scope,
        string $testLevel,
        Configuration $config,
        array $channelScopes
    ): void {
        $this->line('<fg=gray>Summary:</>');

        $rows = [];

        // Global row
        $globalScopes = $config->scopes();
        $globalLevel = $this->getConfiguredLevel($scope, $globalScopes) ?? $config->defaultLevel();
        $globalWouldLog = $globalLevel !== false && $this->wouldLog($testLevel, $globalLevel);
        $rows[] = [
            'channel' => '<fg=gray>global</>',
            'level' => $globalLevel === false ? '<fg=red>SUPPRESSED</>' : $globalLevel,
            'result' => $globalWouldLog ? '<fg=green>logs</>' : '<fg=red>drops</>',
        ];

        // Channel rows
        foreach (array_keys($channelScopes) as $channel) {
            $effectiveScopes = $this->getEffectiveScopes($config, $channel);
            $channelLevel = $this->getConfiguredLevel($scope, $effectiveScopes) ?? $config->defaultLevel();
            $channelWouldLog = $channelLevel !== false && $this->wouldLog($testLevel, $channelLevel);

            $rows[] = [
                'channel' => $channel,
                'level' => $channelLevel === false ? '<fg=red>SUPPRESSED</>' : $channelLevel,
                'result' => $channelWouldLog ? '<fg=green>logs</>' : '<fg=red>drops</>',
            ];
        }

        $this->table(['Channel', 'Level', "Log::{$testLevel}()"], $rows);
    }

    /**
     * Get effective scopes for a channel (merged global + channel-specific).
     *
     * @return array<string, string|false|\Closure>
     */
    protected function getEffectiveScopes(Configuration $config, ?string $channel): array
    {
        $globalScopes = $config->scopes();

        if ($channel === null) {
            return $globalScopes;
        }

        $channelScopes = $config->scopesForChannel($channel);

        return array_merge($globalScopes, $channelScopes);
    }

    /**
     * @param  array<string, string|false|\Closure>  $scopes
     */
    protected function getConfiguredLevel(string $scope, array $scopes): string|false|null
    {
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

    /**
     * @param  array<string, string|false|\Closure>  $scopes
     */
    protected function getMatchedPattern(string $scope, array $scopes): ?string
    {
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
