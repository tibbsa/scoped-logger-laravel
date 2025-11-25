<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Commands;

use Illuminate\Console\Command;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Support\PatternMatcher;

class ListScopesCommand extends Command
{
    protected $signature = 'scoped-logger:list
                            {--sort=name : Sort by name or level}
                            {--channel= : Show scopes for a specific channel only}';

    protected $description = 'List all configured scopes and their log levels';

    public function handle(): int
    {
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);

        if (! $config->isEnabled()) {
            $this->warn('Scoped logger is currently disabled.');
            $this->line('Enable it by setting SCOPED_LOG_ENABLED=true or config.scoped-logger.enabled=true');

            return self::FAILURE;
        }

        $channelFilter = $this->option('channel');
        $scopes = $config->scopes();
        $channelScopes = $config->channelScopes();

        // If filtering by channel, show only that channel's effective scopes
        if (is_string($channelFilter) && $channelFilter !== '') {
            return $this->displayChannelScopes($config, $channelFilter, $scopes, $channelScopes);
        }

        // Show global scopes
        $this->displayGlobalScopes($config, $scopes);

        // Show channel-specific scopes
        $this->displayAllChannelScopes($channelScopes);

        return self::SUCCESS;
    }

    /**
     * Display scopes for a specific channel.
     *
     * @param  array<string, string|false|\Closure>  $globalScopes
     * @param  array<string, array<string, string|false|\Closure>>  $channelScopes
     */
    protected function displayChannelScopes(
        Configuration $config,
        string $channel,
        array $globalScopes,
        array $channelScopes
    ): int {
        $this->info("Effective Scopes for Channel: {$channel}");
        $this->newLine();

        $channelSpecificScopes = $channelScopes[$channel] ?? [];

        // Merge global scopes with channel-specific overrides
        $effectiveScopes = array_merge($globalScopes, $channelSpecificScopes);

        if (empty($effectiveScopes)) {
            $this->line('No scopes configured for this channel.');
            $this->line("<fg=gray>Default Level:</> {$config->defaultLevel()}");

            return self::SUCCESS;
        }

        $rows = $this->buildScopeRows($effectiveScopes, $channelSpecificScopes);
        $rows = $this->sortRows($rows);

        $this->table(
            ['Scope', 'Level', 'Is Pattern?', 'Source'],
            $rows
        );

        $this->newLine();
        $this->line("<fg=gray>Default Level:</> {$config->defaultLevel()}");
        $this->line('<fg=gray>Total Scopes:</> '.count($effectiveScopes));

        if (! empty($channelSpecificScopes)) {
            $this->line('<fg=gray>Channel Overrides:</> '.count($channelSpecificScopes));
        }

        return self::SUCCESS;
    }

    /**
     * Display global scopes.
     *
     * @param  array<string, string|false|\Closure>  $scopes
     */
    protected function displayGlobalScopes(Configuration $config, array $scopes): void
    {
        if (empty($scopes)) {
            $this->info('No global scopes configured.');
            $this->line('Add scopes in config/scoped-logger.php or use SCOPED_LOG_* environment variables.');
            $this->newLine();

            return;
        }

        $this->info('Global Scopes:');
        $this->newLine();

        $rows = $this->buildScopeRows($scopes);
        $rows = $this->sortRows($rows);

        $this->table(
            ['Scope', 'Level', 'Is Pattern?'],
            $rows
        );

        $this->newLine();
        $defaultLevel = $config->defaultLevel();
        $this->line("<fg=gray>Default Level:</> {$defaultLevel}");
        $this->line('<fg=gray>Total Global Scopes:</> '.count($scopes));

        // Show pattern matcher stats if available
        if ($this->hasPatterns($scopes)) {
            $matcher = new PatternMatcher($scopes);
            $stats = $matcher->getCacheStats();
            $this->line("<fg=gray>Pattern Cache:</> {$stats['compiled_patterns']} compiled patterns");
        }

        $this->newLine();
    }

    /**
     * Display all channel-specific scope configurations.
     *
     * @param  array<string, array<string, string|false|\Closure>>  $channelScopes
     */
    protected function displayAllChannelScopes(array $channelScopes): void
    {
        if (empty($channelScopes)) {
            return;
        }

        $this->info('Channel-Specific Scopes:');
        $this->newLine();

        foreach ($channelScopes as $channel => $scopes) {
            if (empty($scopes)) {
                continue;
            }

            $this->line("<fg=cyan>Channel:</> {$channel}");

            $rows = $this->buildScopeRows($scopes);
            $rows = $this->sortRows($rows);

            $this->table(
                ['Scope', 'Level', 'Is Pattern?'],
                $rows
            );

            $this->newLine();
        }

        $this->line('<fg=gray>Total Channels with Overrides:</> '.count($channelScopes));
    }

    /**
     * Build scope rows for table display.
     *
     * @param  array<string, string|false|\Closure>  $scopes
     * @param  array<string, string|false|\Closure>|null  $overrideScopes  Scopes that are overrides (for source column)
     * @return array<int, array<string, string>>
     */
    protected function buildScopeRows(array $scopes, ?array $overrideScopes = null): array
    {
        $rows = [];
        foreach ($scopes as $scope => $level) {
            $levelValue = $this->formatLevel($level);

            $row = [
                'scope' => $scope,
                'level' => $levelValue,
                'pattern' => $this->isPattern($scope) ? '<fg=yellow>Yes</>' : 'No',
            ];

            // Add source column if we're showing effective scopes
            if ($overrideScopes !== null) {
                $row['source'] = isset($overrideScopes[$scope])
                    ? '<fg=cyan>channel</>'
                    : '<fg=gray>global</>';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Format a level value for display.
     *
     * @param  string|false|\Closure  $level
     */
    protected function formatLevel(mixed $level): string
    {
        if ($level instanceof \Closure) {
            $evaluated = $level();

            return $evaluated === false
                ? '<fg=red>SUPPRESSED</>'
                : (is_string($evaluated) ? $evaluated : 'info');
        }

        return $level === false ? '<fg=red>SUPPRESSED</>' : $level;
    }

    /**
     * Sort rows by the configured sort option.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, string>>
     */
    protected function sortRows(array $rows): array
    {
        $sortBy = $this->option('sort');
        if ($sortBy === 'level') {
            usort($rows, fn ($a, $b) => strcmp((string) $a['level'], (string) $b['level']));
        } else {
            usort($rows, fn ($a, $b) => strcmp((string) $a['scope'], (string) $b['scope']));
        }

        return $rows;
    }

    protected function isPattern(string $scope): bool
    {
        return str_contains($scope, '*') || str_contains($scope, '?');
    }

    /**
     * @param  array<string, string|false|\Closure>  $scopes
     */
    protected function hasPatterns(array $scopes): bool
    {
        foreach (array_keys($scopes) as $scope) {
            if ($this->isPattern($scope)) {
                return true;
            }
        }

        return false;
    }
}
