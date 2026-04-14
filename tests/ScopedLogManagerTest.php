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
            ->not->toThrow(Exception::class);
    });
});

describe('ScopedLogManager delegation methods', function () {
    beforeEach(function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.disabled_channels' => [],
            'scoped-logger.scopes' => [
                'payment' => 'debug',
            ],
            'scoped-logger.default_level' => 'info',
        ]);
    });

    it('scope() exists on ScopedLogManager and returns a ScopedLogger', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $result = $manager->scope('payment');

        expect($result)->toBeInstanceOf(ScopedLogger::class);
    });

    it('scope() on manager delegates to the default channel with the scope applied', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $scoped = $manager->scope('payment');

        // Verify that 'payment' was actually passed through to the ScopedLogger's resolver —
        // this would fail if resolveScopedChannel() returned the channel without calling ->scope($scope)
        $ref = new \ReflectionProperty($scoped, 'scopeResolver');
        $ref->setAccessible(true);
        $resolver = $ref->getValue($scoped);

        expect($resolver->getExplicitScopes())->toBe(['payment']);
    });

    it('setRuntimeLevel() exists on ScopedLogManager and returns a ScopedLogger', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $result = $manager->setRuntimeLevel('payment', 'error');

        expect($result)->toBeInstanceOf(ScopedLogger::class);
    });

    it('setRuntimeLevel() on manager forwards to a ScopedLogger instance', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        // Delegation smoke test: call returns a ScopedLogger with the level set on it.
        // We assert on the returned object itself — NOT via $manager->channel() — because
        // ScopedLogManager::channel() constructs a fresh wrapper on every call (pre-existing
        // bug tracked separately). The returned instance is guaranteed to be the same one
        // the mutation ran on.
        $returned = $manager->setRuntimeLevel('payment', 'error');

        expect($returned)->toBeInstanceOf(ScopedLogger::class);
        expect($returned->getRuntimeLevels())->toHaveKey('payment');
        expect($returned->getRuntimeLevels()['payment'])->toBe('error');
    });
});
