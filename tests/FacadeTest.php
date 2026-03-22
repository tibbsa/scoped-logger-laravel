<?php

declare(strict_types=1);

use Tibbs\ScopedLogger\Facades\ScopedLogger;
use Tibbs\ScopedLogger\ScopedLogger as ScopedLoggerInstance;

describe('ScopedLogger Facade', function () {
    beforeEach(function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'test-scope' => 'debug',
            ],
            'scoped-logger.auto_detection' => ['enabled' => false],
        ]);
    });

    it('resolves from the container', function () {
        $resolved = app('scoped-logger');

        expect($resolved)->toBeInstanceOf(ScopedLoggerInstance::class);
    });

    it('works via the facade', function () {
        expect(fn () => ScopedLogger::scope('test-scope')->info('test message'))
            ->not->toThrow(Exception::class);
    });
});
