<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tibbs\ScopedLogger\Commands\ListScopesCommand;
use Tibbs\ScopedLogger\Commands\TestScopeCommand;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Configuration\Validator;

class ScopedLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scoped-logger')
            ->hasConfigFile()
            ->hasCommands([
                ListScopesCommand::class,
                TestScopeCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Extend the Log manager to use our ScopedLogger
        $this->app->extend('log', function (LogManager $logManager, \Illuminate\Contracts\Foundation\Application $app) {
            return new ScopedLogManager($logManager, $app);
        });

        // Also bind ScopedLogger to the container for direct access
        $this->app->singleton('scoped-logger', function ($app) {
            /** @var array<string, mixed> $configArray */
            $configArray = config('scoped-logger', []);
            $logger = Log::channel();

            return new ScopedLogger($logger, $configArray);
        });
    }

    public function packageBooted(): void
    {
        $configArray = $this->getConfigArray();
        $config = Configuration::fromArray($configArray);
        $validator = new Validator;

        $validator->validate($config);
    }

    /**
     * Get configuration array with proper type assertion
     *
     * @return array<string, mixed>
     */
    protected function getConfigArray(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('scoped-logger', []);

        return $config;
    }
}
