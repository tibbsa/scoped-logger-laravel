<?php

namespace TibbsA\ScopedLogger;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScopedLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scoped-logger')
            ->hasConfigFile();
    }
}
