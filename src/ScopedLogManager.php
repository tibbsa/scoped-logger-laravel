<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Illuminate\Log\LogManager;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Contracts\ScopedLoggerContract;

class ScopedLogManager extends LogManager
{
    /** @var array<string, ScopedLoggerContract> */
    protected array $wrappedChannels = [];

    public function __construct(
        protected LogManager $originalLogManager,
        $app
    ) {
        parent::__construct($app);
    }

    /**
     * Get a log channel instance.
     *
     * Always returns a {@see ScopedLoggerContract} — either an active
     * {@see ScopedLogger} (when scoped logging is enabled for this channel)
     * or a {@see PassThroughScopedLogger} (when disabled globally or
     * for this specific channel). This keeps `Log::scope(...)` and other
     * fluent calls safe even when the package is turned off.
     */
    public function channel($channel = null): ScopedLoggerContract
    {
        $logger = $this->originalLogManager->channel($channel);
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);
        $channelName = $channel ?? $this->getDefaultDriver();

        $channelNameString = is_string($channelName) ? $channelName : 'default';

        if (isset($this->wrappedChannels[$channelNameString])) {
            return $this->wrappedChannels[$channelNameString];
        }

        $wrapper = $this->shouldWrapChannel($channelNameString, $config)
            ? new ScopedLogger($logger, $config, $channelNameString)
            : new PassThroughScopedLogger($logger);

        $this->wrappedChannels[$channelNameString] = $wrapper;

        return $wrapper;
    }

    /**
     * Get a log driver instance (alias for channel).
     */
    public function driver($driver = null): ScopedLoggerContract
    {
        return $this->channel($driver);
    }

    /**
     * Check if a channel should be wrapped with ScopedLogger (active scoped
     * logging) versus PassThroughScopedLogger (no-op fallback).
     */
    protected function shouldWrapChannel(string $channel, Configuration $config): bool
    {
        if (! $config->isEnabled()) {
            return false;
        }

        if (in_array($channel, $config->disabledChannels())) {
            return false;
        }

        return true;
    }

    /**
     * @param  string|array<int, string>  $scope
     */
    public function scope(string|array $scope): ScopedLoggerContract
    {
        return $this->channel()->scope($scope);
    }

    public function setRuntimeLevel(string $scope, string|false $level): ScopedLoggerContract
    {
        return $this->channel()->setRuntimeLevel($scope, $level);
    }

    public function clearRuntimeLevel(string $scope): ScopedLoggerContract
    {
        return $this->channel()->clearRuntimeLevel($scope);
    }

    public function clearAllRuntimeLevels(): ScopedLoggerContract
    {
        return $this->channel()->clearAllRuntimeLevels();
    }

    /**
     * @return array<string, string|false>
     */
    public function getRuntimeLevels(): array
    {
        return $this->channel()->getRuntimeLevels();
    }

    /**
     * Dynamically call the default driver instance.
     *
     * This ensures that calls like Log::info(), Log::scope(), etc.
     * go through our wrapped channel, not the original.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        return $this->channel()->$method(...$parameters);
    }
}
