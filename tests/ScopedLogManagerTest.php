<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Tibbs\ScopedLogger\ScopedLogger;
use Tibbs\ScopedLogger\ScopedLogManager;

describe('ScopedLogManager', function () {
    it('wraps default channel with ScopedLogger when enabled', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [],
        ]);

        $logger = Log::channel();

        expect($logger)->toBeInstanceOf(ScopedLogger::class);
    });

    it('returns original logger when scoped logger is disabled', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        $logger = Log::channel();

        expect($logger)->not->toBeInstanceOf(ScopedLogger::class);
    });

    it('returns original logger for disabled channels', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.disabled_channels' => ['stack'],
        ]);

        $logger = Log::channel('stack');

        expect($logger)->not->toBeInstanceOf(ScopedLogger::class);
    });

    it('aliases driver to channel', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.scopes' => [],
        ]);

        $manager = Log::getFacadeRoot();
        expect($manager)->toBeInstanceOf(ScopedLogManager::class);

        $fromDriver = $manager->driver();
        $fromChannel = $manager->channel();

        expect($fromDriver)->toBeInstanceOf(ScopedLogger::class);
        expect($fromChannel)->toBeInstanceOf(ScopedLogger::class);
    });

    it('forwards calls via __call to wrapped channel', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'debug',
            'scoped-logger.scopes' => [],
            'scoped-logger.auto_detection' => ['enabled' => false],
        ]);

        // Log::info() goes through __call -> channel() -> ScopedLogger
        expect(fn () => Log::info('test message'))
            ->not->toThrow(\Exception::class);
    });
});
