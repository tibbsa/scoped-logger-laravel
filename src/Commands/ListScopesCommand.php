<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Commands;

use Illuminate\Console\Command;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Support\PatternMatcher;

class ListScopesCommand extends Command
{
    protected $signature = 'scoped-logger:list
                            {--sort=name : Sort by name or level}';

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

        $scopes = $config->scopes();

        if (empty($scopes)) {
            $this->info('No scopes configured.');
            $this->line('Add scopes in config/scoped-logger.php or use SCOPED_LOG_* environment variables.');

            return self::SUCCESS;
        }

        $this->info('Configured Scopes:');
        $this->newLine();

        // Prepare data for table
        $rows = [];
        foreach ($scopes as $scope => $level) {
            // Evaluate closures to get the actual level
            if ($level instanceof \Closure) {
                $evaluated = $level();
                $levelValue = $evaluated === false ? '<fg=red>SUPPRESSED</>' : (is_string($evaluated) ? $evaluated : 'info');
            } else {
                $levelValue = $level === false ? '<fg=red>SUPPRESSED</>' : $level;
            }

            $rows[] = [
                'scope' => $scope,
                'level' => $levelValue,
                'pattern' => $this->isPattern($scope) ? '<fg=yellow>Yes</>' : 'No',
            ];
        }

        // Sort rows
        $sortBy = $this->option('sort');
        if ($sortBy === 'level') {
            usort($rows, fn ($a, $b) => strcmp((string) $a['level'], (string) $b['level']));
        } else {
            usort($rows, fn ($a, $b) => strcmp((string) $a['scope'], (string) $b['scope']));
        }

        $this->table(
            ['Scope', 'Level', 'Is Pattern?'],
            $rows
        );

        $this->newLine();
        $defaultLevel = $config->defaultLevel();
        $this->line("<fg=gray>Default Level:</> {$defaultLevel}");
        $this->line('<fg=gray>Total Scopes:</> '.count($scopes));

        // Show pattern matcher stats if available
        if ($this->hasPatterns($scopes)) {
            $matcher = new PatternMatcher($scopes);
            $stats = $matcher->getCacheStats();
            $this->newLine();
            $this->line("<fg=gray>Pattern Cache:</> {$stats['compiled_patterns']} compiled patterns");
        }

        return self::SUCCESS;
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
