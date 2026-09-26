<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use LogicException;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Configuration\Configuration;

class ScopedLogManager extends LogManager
{
    /** @var array<string, ScopedLogger> */
    protected array $wrappedChannels = [];

    public function __construct(
        protected LogManager $originalLogManager,
        $app
    ) {
        parent::__construct($app);
    }

    /**
     * Get a log channel instance, wrapped in ScopedLogger
     */
    public function channel($channel = null): LoggerInterface
    {
        $logger = $this->originalLogManager->channel($channel);
        /** @var array<string, mixed> $configArray */
        $configArray = config('scoped-logger', []);
        $config = Configuration::fromArray($configArray);
        $channelNameString = $this->channelName($channel);

        // Check if this channel should be wrapped
        if ($this->shouldWrapChannel($channelNameString, $config)) {
            if (! isset($this->wrappedChannels[$channelNameString])) {
                $this->wrappedChannels[$channelNameString] = new ScopedLogger($logger, $config, $channelNameString);
            }

            return $this->wrappedChannels[$channelNameString];
        }

        return $logger;
    }

    /**
     * Get a log driver instance (alias for channel)
     */
    public function driver($driver = null): LoggerInterface
    {
        return $this->channel($driver);
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
     * @param  string|null  $driver
     */
    public function forgetChannel($driver = null): void
    {
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
     * Resolve the name used to cache a channel's ScopedLogger wrapper
     */
    protected function channelName(mixed $channel): string
    {
        $channelName = $channel ?? $this->getDefaultDriver();

        return is_string($channelName) ? $channelName : 'default';
    }

    /**
     * Check if a channel should be wrapped with ScopedLogger
     */
    protected function shouldWrapChannel(string $channel, Configuration $config): bool
    {
        // If scoped logger is disabled globally, don't wrap
        if (! $config->isEnabled()) {
            return false;
        }

        // Check if channel is in disabled list
        if (in_array($channel, $config->disabledChannels())) {
            return false;
        }

        // Default: wrap all channels (global by default)
        return true;
    }

    /**
     * Resolve the default channel and assert it is a ScopedLogger.
     *
     * Used by the explicit delegation methods below so static analyzers
     * (phpstan, larastan) can see that package-specific methods like
     * scope() and setRuntimeLevel() exist on the class bound to `log`.
     */
    private function resolveScopedChannel(string $method): ScopedLogger
    {
        $channel = $this->channel();

        if (! $channel instanceof ScopedLogger) {
            throw new LogicException(sprintf(
                'Cannot call %s() on the default channel because it is not wrapped by ScopedLogger. '
                .'Check that scoped-logger is enabled and the default channel is not listed in '
                .'scoped-logger.disabled_channels. Use Log::channel(\'other\')->%s(...) '
                .'to target a specific scoped channel directly.',
                $method,
                $method
            ));
        }

        return $channel;
    }

    /**
     * @param  string|array<int, string>  $scope
     */
    public function scope(string|array $scope): ScopedLogger
    {
        return $this->resolveScopedChannel(__FUNCTION__)->scope($scope);
    }

    public function setRuntimeLevel(string $scope, string|false $level): ScopedLogger
    {
        return $this->resolveScopedChannel(__FUNCTION__)->setRuntimeLevel($scope, $level);
    }

    public function clearRuntimeLevel(string $scope): ScopedLogger
    {
        return $this->resolveScopedChannel(__FUNCTION__)->clearRuntimeLevel($scope);
    }

    public function clearAllRuntimeLevels(): ScopedLogger
    {
        return $this->resolveScopedChannel(__FUNCTION__)->clearAllRuntimeLevels();
    }

    /**
     * @return array<string, string|false>
     */
    public function getRuntimeLevels(): array
    {
        return $this->resolveScopedChannel(__FUNCTION__)->getRuntimeLevels();
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
