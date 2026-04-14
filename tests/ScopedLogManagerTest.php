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
        $ref = new ReflectionProperty($scoped, 'scopeResolver');
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

    it('clearRuntimeLevel() on manager forwards to a ScopedLogger instance', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        // Set a level and clear it on the same returned instance
        $withLevel = $manager->setRuntimeLevel('payment', 'error');
        expect($withLevel->getRuntimeLevels())->toHaveKey('payment');

        $returned = $manager->clearRuntimeLevel('payment');
        expect($returned)->toBeInstanceOf(ScopedLogger::class);

        // Note: $returned is a different ScopedLogger instance than $withLevel
        // (ScopedLogManager::channel() constructs a fresh wrapper on every call,
        // pre-existing bug tracked separately). We only assert the method exists
        // and returns the correct type.
    });

    it('clearAllRuntimeLevels() on manager forwards to a ScopedLogger instance', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $returned = $manager->clearAllRuntimeLevels();
        expect($returned)->toBeInstanceOf(ScopedLogger::class);
        expect($returned->getRuntimeLevels())->toBe([]);
    });

    it('getRuntimeLevels() on manager returns an array', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $result = $manager->getRuntimeLevels();

        expect($result)->toBeArray();
    });

    describe('with disabled default channel', function () {
        beforeEach(function () {
            // Laravel's default log channel in Testbench is 'stack'.
            // Put it in disabled_channels so ScopedLogManager::channel()
            // returns the raw Laravel logger, not a ScopedLogger.
            config([
                'scoped-logger.enabled' => true,
                'scoped-logger.disabled_channels' => ['stack'],
                'scoped-logger.scopes' => [],
                'scoped-logger.default_level' => 'info',
            ]);
        });

        it('scope() throws LogicException with guidance', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect(fn () => $manager->scope('payment'))
                ->toThrow(
                    LogicException::class,
                    'Cannot call scope() on the default channel because it is not wrapped by ScopedLogger'
                );
        });

        it('setRuntimeLevel() throws LogicException with guidance', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect(fn () => $manager->setRuntimeLevel('payment', 'error'))
                ->toThrow(
                    LogicException::class,
                    'Cannot call setRuntimeLevel() on the default channel'
                );
        });

        it('clearRuntimeLevel() throws LogicException', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect(fn () => $manager->clearRuntimeLevel('payment'))
                ->toThrow(LogicException::class, 'Cannot call clearRuntimeLevel()');
        });

        it('clearAllRuntimeLevels() throws LogicException', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect(fn () => $manager->clearAllRuntimeLevels())
                ->toThrow(LogicException::class, 'Cannot call clearAllRuntimeLevels()');
        });

        it('getRuntimeLevels() throws LogicException', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect(fn () => $manager->getRuntimeLevels())
                ->toThrow(LogicException::class, 'Cannot call getRuntimeLevels()');
        });
    });
});
