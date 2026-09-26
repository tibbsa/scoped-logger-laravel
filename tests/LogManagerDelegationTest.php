<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tibbs\ScopedLogger\ScopedLogManager;

/**
 * Manager-level LogManager methods must act on the real (wrapped) LogManager,
 * not on the ScopedLogManager decorator's own, never-populated state.
 */
describe('LogManager delegation', function () {
    beforeEach(function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'debug',
            'scoped-logger.scopes' => [],
            'scoped-logger.auto_detection.enabled' => false,
            'logging.default' => 'null',
        ]);

        $this->logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });
    });

    it('uses custom drivers registered with Log::extend()', function () {
        $handler = new TestHandler;
        Log::extend('capture', fn () => new Monolog('capture', [$handler]));
        config(['logging.channels.captured' => ['driver' => 'capture']]);

        Log::channel('captured')->info('hello');

        expect($handler->getRecords())->toHaveCount(1)
            ->and($handler->getRecords()[0]->message)->toBe('hello');
    });

    it('binds Log::extend() callbacks to the underlying log manager', function () {
        $boundTo = null;
        Log::extend('capture', function () use (&$boundTo) {
            $boundTo = $this;

            return new Monolog('capture', [new TestHandler]);
        });
        config(['logging.channels.captured' => ['driver' => 'capture']]);

        Log::channel('captured');

        expect($boundTo)->toBeInstanceOf(LogManager::class)
            ->and($boundTo)->not->toBeInstanceOf(ScopedLogManager::class);
    });

    it('applies Log::shareContext() to channels resolved afterwards', function () {
        Log::shareContext(['request_id' => 'abc']);

        Log::channel('null')->info('hello');

        expect($this->logged[0]->context)->toMatchArray(['request_id' => 'abc']);
    });

    it('applies Log::shareContext() to channels resolved beforehand', function () {
        $channel = Log::channel('null');

        Log::shareContext(['request_id' => 'abc']);
        $channel->info('hello');

        expect($this->logged[0]->context)->toMatchArray(['request_id' => 'abc']);
    });

    it('applies Log::shareContext() to on-demand stacks', function () {
        Log::shareContext(['request_id' => 'abc']);

        Log::stack(['null'])->info('hello');

        expect($this->logged[0]->context)->toMatchArray(['request_id' => 'abc']);
    });

    it('applies Log::shareContext() to on-demand built channels', function () {
        Log::shareContext(['request_id' => 'abc']);

        Log::build(['driver' => 'monolog', 'handler' => NullHandler::class])->info('hello');

        expect($this->logged[0]->context)->toMatchArray(['request_id' => 'abc']);
    });

    it('reports shared context via Log::sharedContext()', function () {
        Log::shareContext(['request_id' => 'abc']);

        expect(Log::sharedContext())->toBe(['request_id' => 'abc']);
    });

    it('stops sharing context with new channels after Log::flushSharedContext()', function () {
        Log::shareContext(['request_id' => 'abc']);
        Log::flushSharedContext();

        Log::channel('null')->info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('request_id');
    });

    it('clears facade-level context with Log::withoutContext()', function () {
        Log::withContext(['user_id' => 1]);
        Log::withoutContext();

        Log::info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('user_id');
    });

    it('clears shared context from resolved channels with Log::withoutContext()', function () {
        $channel = Log::channel('null');
        Log::shareContext(['request_id' => 'abc']);
        Log::withoutContext();

        $channel->info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('request_id');
    });

    it('clears shared context from unwrapped channels with Log::withoutContext()', function () {
        config(['scoped-logger.disabled_channels' => ['null']]);
        $channel = Log::channel('null');
        Log::shareContext(['request_id' => 'abc']);
        Log::withoutContext();

        $channel->info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('request_id');
    });

    it('clears shared context from a channel with withoutContext() on that channel', function () {
        $channel = Log::channel('null');
        Log::shareContext(['request_id' => 'abc']);
        $channel->withoutContext();

        $channel->info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('request_id');
    });

    it('clears only the given keys with Log::withoutContext($keys)', function () {
        Log::withContext(['user_id' => 1, 'tenant' => 'acme']);
        Log::withoutContext(['user_id']);

        Log::info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('user_id')
            ->and($this->logged[0]->context)->toMatchArray(['tenant' => 'acme']);
    });

    it('clears only the given keys with withoutContext($keys) on a channel', function () {
        $channel = Log::channel('null');
        $channel->withContext(['user_id' => 1, 'tenant' => 'acme']);
        $channel->withoutContext(['user_id']);

        $channel->info('hello');

        expect($this->logged[0]->context)->not->toHaveKey('user_id')
            ->and($this->logged[0]->context)->toMatchArray(['tenant' => 'acme']);
    });

    it('rebuilds the channel after Log::forgetChannel()', function () {
        $builds = 0;
        Log::extend('counting', function () use (&$builds) {
            $builds++;

            return new Monolog('counting', [new TestHandler]);
        });
        config(['logging.channels.counted' => ['driver' => 'counting']]);

        Log::channel('counted');
        Log::forgetChannel('counted');
        Log::channel('counted');

        expect($builds)->toBe(2);
    });

    it('drops the scoped wrapper state after Log::forgetChannel()', function () {
        Log::channel('null')->setRuntimeLevel('payment', 'error');

        Log::forgetChannel('null');

        expect(Log::channel('null')->getRuntimeLevels())->toBe([]);
    });

    it('lists resolved channels via Log::getChannels()', function () {
        Log::channel('null');

        expect(Log::getChannels())->toHaveKey('null');
    });

    it('passes a new application instance to the underlying log manager', function () {
        $original = (fn () => $this->originalLogManager)->call(Log::getFacadeRoot());
        $newApp = clone app();

        Log::setApplication($newApp);

        expect((fn () => $this->app)->call($original))->toBe($newApp);
    });

    it('overrides every stateful public LogManager method', function () {
        // Methods that are safe to inherit: they route through channel()/driver()
        // (overridden) or only touch config via $this->app (kept in sync by setApplication()).
        $safeToInherit = [
            'getDefaultDriver', 'setDefaultDriver',
            'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log',
        ];

        $unhandled = collect((new ReflectionClass(LogManager::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method) => in_array($method->getName(), $safeToInherit, true))
            ->reject(fn (ReflectionMethod $method) => (new ReflectionMethod(ScopedLogManager::class, $method->getName()))
                ->getDeclaringClass()->getName() === ScopedLogManager::class)
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->values()
            ->all();

        expect($unhandled)->toBe([]);
    });
});
