<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Contracts\ScopedLoggerContract;
use Tibbs\ScopedLogger\PassThroughScopedLogger;
use Tibbs\ScopedLogger\ScopedLogger;

describe('PassThroughScopedLogger - unit', function () {
    beforeEach(function () {
        $this->underlying = Mockery::mock(LoggerInterface::class);
        $this->passThrough = new PassThroughScopedLogger($this->underlying);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('implements ScopedLoggerContract', function () {
        expect($this->passThrough)->toBeInstanceOf(ScopedLoggerContract::class);
    });

    it('scope() is a no-op returning itself for chaining', function () {
        expect($this->passThrough->scope('anything'))->toBe($this->passThrough);
        expect($this->passThrough->scope(['a', 'b']))->toBe($this->passThrough);
    });

    it('setRuntimeLevel() is a no-op returning itself', function () {
        expect($this->passThrough->setRuntimeLevel('payment', 'debug'))->toBe($this->passThrough);
        expect($this->passThrough->setRuntimeLevel('payment', false))->toBe($this->passThrough);
    });

    it('clearRuntimeLevel() is a no-op returning itself', function () {
        expect($this->passThrough->clearRuntimeLevel('payment'))->toBe($this->passThrough);
    });

    it('clearAllRuntimeLevels() is a no-op returning itself', function () {
        expect($this->passThrough->clearAllRuntimeLevels())->toBe($this->passThrough);
    });

    it('getRuntimeLevels() always returns an empty array', function () {
        expect($this->passThrough->getRuntimeLevels())->toBe([]);

        // Even after calling setRuntimeLevel, it remains empty
        $this->passThrough->setRuntimeLevel('payment', 'debug');
        expect($this->passThrough->getRuntimeLevels())->toBe([]);
    });

    it('withContext() and withoutContext() are no-ops returning itself', function () {
        expect($this->passThrough->withContext(['x' => 1]))->toBe($this->passThrough);
        expect($this->passThrough->withoutContext())->toBe($this->passThrough);
    });

    it('forwards info() to the underlying logger unchanged', function () {
        $this->underlying->shouldReceive('info')
            ->once()
            ->with('hello', ['ctx' => 1]);

        $this->passThrough->info('hello', ['ctx' => 1]);
    });

    it('forwards each PSR-3 severity method to the underlying logger', function () {
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $method) {
            $this->underlying->shouldReceive($method)
                ->once()
                ->with('msg', ['k' => 'v']);

            $this->passThrough->$method('msg', ['k' => 'v']);
        }
    });

    it('forwards log() with arbitrary level to the underlying logger', function () {
        $this->underlying->shouldReceive('log')
            ->once()
            ->with('warning', 'msg', ['k' => 'v']);

        $this->passThrough->log('warning', 'msg', ['k' => 'v']);
    });

    it('does not mutate context passed through scoped API methods', function () {
        $this->underlying->shouldReceive('info')
            ->once()
            ->with('hi', []); // no scope/metadata added

        $this->passThrough->scope('payment')->info('hi');
    });

    it('forwards unknown method calls to the underlying logger via __call', function () {
        // Laravel's logger exposes extensions beyond PSR-3 (e.g. getLogger()).
        // Anything not on the scoped-logger API should transparently forward.
        $underlying = new class implements LoggerInterface
        {
            /** @var array<int, array{method: string, args: array<int, mixed>}> */
            public array $calls = [];

            public function emergency(string|Stringable $message, array $context = []): void {}

            public function alert(string|Stringable $message, array $context = []): void {}

            public function critical(string|Stringable $message, array $context = []): void {}

            public function error(string|Stringable $message, array $context = []): void {}

            public function warning(string|Stringable $message, array $context = []): void {}

            public function notice(string|Stringable $message, array $context = []): void {}

            public function info(string|Stringable $message, array $context = []): void {}

            public function debug(string|Stringable $message, array $context = []): void {}

            public function log($level, string|Stringable $message, array $context = []): void {}

            public function write(string $message, string $channel): string
            {
                $this->calls[] = ['method' => 'write', 'args' => [$message, $channel]];

                return "wrote:{$message}:{$channel}";
            }
        };

        $passThrough = new PassThroughScopedLogger($underlying);

        $result = $passThrough->write('hello', 'stack');

        expect($result)->toBe('wrote:hello:stack');
        expect($underlying->calls)->toBe([
            ['method' => 'write', 'args' => ['hello', 'stack']],
        ]);
    });
});

describe('PassThroughScopedLogger - integration via Log facade', function () {
    it('chained scope()->info() when globally disabled does not throw', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        expect(fn () => Log::scope('anything')->info('hello'))
            ->not->toThrow(Exception::class);
    });

    it('Log::channel() returns PassThroughScopedLogger when globally disabled', function () {
        config([
            'scoped-logger.enabled' => false,
        ]);

        $channel = Log::channel();

        expect($channel)->toBeInstanceOf(PassThroughScopedLogger::class);
        expect($channel)->not->toBeInstanceOf(ScopedLogger::class);
    });

    it('disabled specific channel: scoped API is safe on that channel', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.disabled_channels' => ['stack'],
        ]);

        expect(fn () => Log::channel('stack')->scope('x')->setRuntimeLevel('x', 'debug')->info('msg'))
            ->not->toThrow(Exception::class);
    });

    it('enabled config still returns an active ScopedLogger (not pass-through)', function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.disabled_channels' => [],
            'scoped-logger.default_level' => 'debug',
            'scoped-logger.scopes' => [],
            'scoped-logger.auto_detection' => ['enabled' => false],
        ]);

        $channel = Log::channel();

        expect($channel)->toBeInstanceOf(ScopedLogger::class);
        expect($channel)->not->toBeInstanceOf(PassThroughScopedLogger::class);
    });
});
