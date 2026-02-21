<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\ScopedLogger;
use Tibbs\ScopedLogger\ScopedLogManager;

describe('ScopedLoggerServiceProvider', function () {
    it('registers scoped-logger singleton in container', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [],
            'scoped-logger.auto_detection' => ['enabled' => false],
        ]);

        $resolved = app('scoped-logger');

        expect($resolved)->toBeInstanceOf(ScopedLogger::class);
    });

    it('extends the log manager with ScopedLogManager', function () {
        $logManager = app('log');

        expect($logManager)->toBeInstanceOf(ScopedLogManager::class);
    });

    it('resolves scoped-logger singleton consistently', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [],
        ]);

        $first = app('scoped-logger');
        $second = app('scoped-logger');

        expect($first)->toBe($second);
    });
});
