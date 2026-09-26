<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tibbs\ScopedLogger\PassThroughScopedLogger;

/**
 * On-demand channels from Log::stack() and Log::build() are filtered like
 * configured channels. Stacks are named by their $channel argument (default
 * 'stack'); built channels use Laravel's 'ondemand' name.
 */
describe('On-demand channels', function () {
    beforeEach(function () {
        config([
            'scoped-logger.enabled' => true,
            'scoped-logger.default_level' => 'debug',
            'scoped-logger.scopes' => ['payment' => 'error'],
            'scoped-logger.auto_detection.enabled' => false,
        ]);

        $this->logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event->message;
        });
    });

    it('filters Log::stack() by scope level', function () {
        $stack = Log::stack(['null']);

        $stack->scope('payment')->info('filtered');
        $stack->scope('payment')->error('kept');

        expect($this->logged)->toBe(['kept']);
    });

    it('filters Log::build() by scope level', function () {
        $channel = Log::build(['driver' => 'monolog', 'handler' => NullHandler::class]);

        $channel->scope('payment')->info('filtered');
        $channel->scope('payment')->error('kept');

        expect($this->logged)->toBe(['kept']);
    });

    it('applies channel_scopes for a named stack', function () {
        config(['scoped-logger.channel_scopes' => ['audit' => ['payment' => 'debug']]]);

        Log::stack(['null'], 'audit')->scope('payment')->debug('kept');

        expect($this->logged)->toBe(['kept']);
    });

    it('applies channel_scopes named "stack" to an unnamed stack', function () {
        config(['scoped-logger.channel_scopes' => ['stack' => ['payment' => 'debug']]]);

        Log::stack(['null'])->scope('payment')->debug('kept');

        expect($this->logged)->toBe(['kept']);
    });

    it('applies channel_scopes named "ondemand" to built channels', function () {
        config(['scoped-logger.channel_scopes' => ['ondemand' => ['payment' => 'debug']]]);

        Log::build(['driver' => 'monolog', 'handler' => NullHandler::class])->scope('payment')->debug('kept');

        expect($this->logged)->toBe(['kept']);
    });

    it('passes through a stack whose name is in disabled_channels', function () {
        config(['scoped-logger.disabled_channels' => ['audit']]);

        expect(Log::stack(['null'], 'audit'))->toBeInstanceOf(PassThroughScopedLogger::class);
    });

    it('passes through built channels when "ondemand" is in disabled_channels', function () {
        config(['scoped-logger.disabled_channels' => ['ondemand']]);

        expect(Log::build(['driver' => 'monolog', 'handler' => NullHandler::class]))
            ->toBeInstanceOf(PassThroughScopedLogger::class);
    });

    it('keeps scope() usable on on-demand channels when scoped logging is disabled', function () {
        config(['scoped-logger.enabled' => false]);

        Log::stack(['null'])->scope('payment')->info('from stack');
        Log::build(['driver' => 'monolog', 'handler' => NullHandler::class])->scope('payment')->info('from build');

        expect($this->logged)->toBe(['from stack', 'from build']);
    });

    it('sends each stack to its own channels', function () {
        $first = new TestHandler;
        $second = new TestHandler;
        Log::extend('first', fn () => new Monolog('first', [$first]));
        Log::extend('second', fn () => new Monolog('second', [$second]));
        config([
            'logging.channels.first' => ['driver' => 'first'],
            'logging.channels.second' => ['driver' => 'second'],
        ]);

        Log::stack(['first'])->info('to first');
        Log::stack(['second'])->info('to second');

        expect(collect($first->getRecords())->pluck('message')->all())->toBe(['to first'])
            ->and(collect($second->getRecords())->pluck('message')->all())->toBe(['to second']);
    });
});
