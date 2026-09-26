<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Tibbs\ScopedLogger\Contracts\ScopedLoggerContract;
use Tibbs\ScopedLogger\PassThroughScopedLogger;
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

    it('returns a PassThroughScopedLogger when scoped logger is disabled', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        $logger = Log::channel();

        expect($logger)->toBeInstanceOf(PassThroughScopedLogger::class);
        expect($logger)->not->toBeInstanceOf(ScopedLogger::class);
    });

    it('returns a PassThroughScopedLogger for disabled channels', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.disabled_channels' => ['stack'],
        ]);

        $logger = Log::channel('stack');

        expect($logger)->toBeInstanceOf(PassThroughScopedLogger::class);
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

    describe('with disabled default channel (pass-through mode)', function () {
        beforeEach(function () {
            // Laravel's default log channel in Testbench is 'stack'.
            // Put it in disabled_channels so ScopedLogManager::channel()
            // returns a PassThroughScopedLogger, not an active ScopedLogger.
            config([
                'scoped-logger.enabled' => true,
                'scoped-logger.disabled_channels' => ['stack'],
                'scoped-logger.scopes' => [],
                'scoped-logger.default_level' => 'info',
            ]);
        });

        it('scope() silently no-ops and returns a ScopedLoggerContract', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            $result = $manager->scope('payment');

            expect($result)->toBeInstanceOf(ScopedLoggerContract::class);
            expect($result)->toBeInstanceOf(PassThroughScopedLogger::class);
        });

        it('setRuntimeLevel() silently no-ops', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            $result = $manager->setRuntimeLevel('payment', 'error');

            expect($result)->toBeInstanceOf(PassThroughScopedLogger::class);
            expect($result->getRuntimeLevels())->toBe([]);
        });

        it('clearRuntimeLevel() silently no-ops', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            $result = $manager->clearRuntimeLevel('payment');

            expect($result)->toBeInstanceOf(PassThroughScopedLogger::class);
        });

        it('clearAllRuntimeLevels() silently no-ops', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            $result = $manager->clearAllRuntimeLevels();

            expect($result)->toBeInstanceOf(PassThroughScopedLogger::class);
            expect($result->getRuntimeLevels())->toBe([]);
        });

        it('getRuntimeLevels() returns an empty array', function () {
            $manager = Log::getFacadeRoot();
            assert($manager instanceof ScopedLogManager);

            expect($manager->getRuntimeLevels())->toBe([]);
        });

        it('Log::scope(...)->info(...) works end-to-end without error', function () {
            expect(fn () => Log::scope('anything')->info('test message'))
                ->not->toThrow(Exception::class);
        });

        it('chained scope with runtime level works without error', function () {
            expect(fn () => Log::scope('payment')->setRuntimeLevel('payment', 'debug')->info('msg'))
                ->not->toThrow(Exception::class);
        });
    });
});

describe('ScopedLogManager channel caching', function () {
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

    it('returns the same ScopedLogger instance for the same channel', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $first = $manager->channel();
        $second = $manager->channel();

        expect($first)->toBe($second);
    });

    it('returns different ScopedLogger instances for different channels', function () {
        config(['logging.channels.single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log')]]);

        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $default = $manager->channel();
        $single = $manager->channel('single');

        expect($default)->not->toBe($single);
    });

    it('preserves runtime levels set via the manager across channel calls', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $channel = $manager->channel();
        assert($channel instanceof ScopedLogger);
        $channel->setRuntimeLevel('payment', 'error');

        $sameChannel = $manager->channel();
        assert($sameChannel instanceof ScopedLogger);

        expect($sameChannel->getRuntimeLevels())->toHaveKey('payment');
        expect($sameChannel->getRuntimeLevels()['payment'])->toBe('error');
    });

    it('preserves shared context across channel calls', function () {
        $manager = Log::getFacadeRoot();
        assert($manager instanceof ScopedLogManager);

        $channel = $manager->channel();
        assert($channel instanceof ScopedLogger);
        $channel->withContext(['request_id' => 'abc123']);

        $sameChannel = $manager->channel();

        // The same instance should have the context
        expect($sameChannel)->toBe($channel);
    });
});
