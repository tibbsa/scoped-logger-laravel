<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Tibbs\ScopedLogger\ScopedLogger;

enum BackedLogChannel: string
{
    case Payments = 'payments';
    case Audit = 'audit';
}

enum PureLogChannel
{
    case payments;
}

/**
 * Laravel 13 accepts UnitEnum channel names in Log::channel(), driver() and
 * forgetChannel(). Enum names must resolve exactly like their string form.
 */
describe('Enum channel names', function () {
    beforeEach(function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'debug',
            'scoped-logger.scopes' => ['payment' => 'debug'],
            'scoped-logger.auto_detection.enabled' => false,
            'logging.channels.payments' => ['driver' => 'monolog', 'handler' => NullHandler::class],
            'logging.channels.audit' => ['driver' => 'monolog', 'handler' => NullHandler::class],
        ]);

        $this->logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });
    });

    it('resolves a backed enum to the same wrapper as its string value', function () {
        expect(Log::channel(BackedLogChannel::Payments))->toBe(Log::channel('payments'));
    });

    it('resolves a pure enum to the same wrapper as its case name', function () {
        expect(Log::channel(PureLogChannel::payments))->toBe(Log::channel('payments'));
    });

    it('resolves different enum cases to different wrappers', function () {
        expect(Log::channel(BackedLogChannel::Payments))->not->toBe(Log::channel(BackedLogChannel::Audit));
    });

    it('resolves enums passed to Log::driver()', function () {
        expect(Log::driver(BackedLogChannel::Payments))->toBe(Log::channel('payments'));
    });

    it('uses an enum passed to Log::setDefaultDriver() as the default channel', function () {
        Log::setDefaultDriver(BackedLogChannel::Payments);

        expect(Log::channel())->toBe(Log::channel('payments'));
    });

    it('does not wrap an enum channel listed in disabled_channels', function () {
        config(['scoped-logger.disabled_channels' => ['payments']]);

        expect(Log::channel(BackedLogChannel::Payments))->not->toBeInstanceOf(ScopedLogger::class);
    });

    it('applies channel_scopes to an enum channel', function () {
        config(['scoped-logger.channel_scopes' => ['payments' => ['payment' => 'error']]]);

        Log::channel(BackedLogChannel::Payments)->scope('payment')->info('filtered');
        Log::channel(BackedLogChannel::Payments)->scope('payment')->error('kept');

        expect(collect($this->logged)->pluck('message')->all())->toBe(['kept']);
    });

    it('drops the wrapper for an enum passed to Log::forgetChannel()', function () {
        Log::channel('payments')->setRuntimeLevel('payment', 'error');

        Log::forgetChannel(BackedLogChannel::Payments);

        expect(Log::channel('payments')->getRuntimeLevels())->toBe([]);
    });
});
