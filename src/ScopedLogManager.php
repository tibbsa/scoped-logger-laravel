<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Configuration\Configuration;
use Tibbs\ScopedLogger\Contracts\ScopedLoggerContract;
use UnitEnum;

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
     *
     * @param  UnitEnum|string|null  $channel
     */
    public function channel($channel = null): ScopedLoggerContract
    {
        $channel = $this->normalizeChannel($channel);
        $logger = $this->originalLogManager->channel($channel);
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);
        $channelNameString = $this->channelName($channel);

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
     *
     * @param  UnitEnum|string|null  $driver
     */
    public function driver($driver = null): ScopedLoggerContract
    {
        return $this->channel($driver);
    }

    /**
     * Set the default log driver name
     *
     * @param  UnitEnum|string  $name
     */
    public function setDefaultDriver($name): void
    {
        parent::setDefaultDriver($this->normalizeChannel($name));
    }

    /**
     * Build an on-demand channel using the underlying log manager
     *
     * @param  array<string, mixed>  $config
     */
    public function build(array $config): LoggerInterface
    {
        return $this->originalLogManager->build($config);
    }

    /**
     * Create an on-demand stack channel using the underlying log manager
     *
     * @param  array<int, string>  $channels
     * @param  string|null  $channel
     */
    public function stack(array $channels, $channel = null): LoggerInterface
    {
        return $this->originalLogManager->stack($channels, $channel);
    }

    /**
     * Share context across all channels of the underlying log manager
     *
     * @param  array<string, mixed>  $context
     */
    public function shareContext(array $context): static
    {
        $this->originalLogManager->shareContext($context);

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function sharedContext(): array
    {
        return $this->originalLogManager->sharedContext();
    }

    /**
     * Flush context on all resolved channels, including scoped wrappers
     *
     * @param  string[]|null  $keys
     */
    public function withoutContext(?array $keys = null): static
    {
        $this->originalLogManager->withoutContext($keys);

        foreach ($this->wrappedChannels as $channel) {
            $channel->withoutContext($keys);
        }

        return $this;
    }

    public function flushSharedContext(): static
    {
        $this->originalLogManager->flushSharedContext();

        return $this;
    }

    /**
     * Register a custom driver creator on the underlying log manager
     *
     * @param  string  $driver
     */
    public function extend($driver, Closure $callback): static
    {
        $this->originalLogManager->extend($driver, $callback);

        return $this;
    }

    /**
     * Forget a resolved channel and its scoped wrapper
     *
     * @param  UnitEnum|string|null  $driver
     */
    public function forgetChannel($driver = null): void
    {
        $driver = $this->normalizeChannel($driver);
        $this->originalLogManager->forgetChannel($driver);

        unset($this->wrappedChannels[$this->channelName($driver)]);
    }

    /**
     * @return array<mixed>
     */
    public function getChannels(): array
    {
        return $this->originalLogManager->getChannels();
    }

    /**
     * Set the application instance on this and the underlying log manager
     *
     * @param  Application  $app
     */
    public function setApplication($app): static
    {
        parent::setApplication($app);
        $this->originalLogManager->setApplication($app);

        return $this;
    }

    /**
     * Convert an enum channel name to its string form, as Laravel's enum_value() does
     *
     * @return ($channel is null ? null : string)
     */
    protected function normalizeChannel(UnitEnum|string|null $channel): ?string
    {
        return match (true) {
            $channel instanceof BackedEnum => (string) $channel->value,
            $channel instanceof UnitEnum => $channel->name,
            default => $channel,
        };
    }

    /**
     * Resolve the name used to cache a channel's ScopedLogger wrapper
     */
    protected function channelName(mixed $channel): string
    {
        $channelName = $channel ?? $this->getDefaultDriver();

        return is_string($channelName) ? $channelName : 'default';
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
