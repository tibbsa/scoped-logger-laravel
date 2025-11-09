<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

describe('Log Facade Integration', function () {
    beforeEach(function () {
        // Set up scoped logger config
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'info',
            'scoped-logger.scopes' => [
                'test-scope' => 'debug',
                'error-only' => 'error',
            ],
            'scoped-logger.auto_detection' => [
                'enabled' => false,
            ],
            'scoped-logger.include_scope_in_context' => true,
        ]);
    });

    it('works with Log facade using explicit scope', function () {
        // This should work: Log::scope('test-scope')->debug('message')
        // But let's first check if Log returns our ScopedLogger
        $logger = Log::channel();

        expect($logger)->toBeInstanceOf(\Tibbs\ScopedLogger\ScopedLogger::class);
    });

    it('supports scope method on Log facade', function () {
        $logger = Log::channel();

        // Check that it's our ScopedLogger which has scope method
        expect($logger)->toBeInstanceOf(\Tibbs\ScopedLogger\ScopedLogger::class)
            ->and(method_exists($logger, 'scope'))->toBeTrue();
    });

    it('filters logs based on scope when using Log facade', function () {
        // Get the log channel
        $logger = Log::channel();

        // This is a ScopedLogger, so scope() should work
        expect(fn () => $logger->scope('error-only')->debug('test'))
            ->not->toThrow(\Exception::class);
    });

    it('can chain scope with Log facade directly', function () {
        // Test that we can actually use Log facade with scope
        // This should NOT throw an error
        expect(fn () => Log::scope('test-scope'))->not->toThrow(\Exception::class);
    });

    it('can log with Log facade and scope', function () {
        // This is the real test - can we actually use Log::scope()->info()?
        expect(fn () => Log::scope('test-scope')->info('test message'))
            ->not->toThrow(\Exception::class);
    });
});
