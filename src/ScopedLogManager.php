<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Illuminate\Log\LogManager;
use LogicException;
use Psr\Log\LoggerInterface;
use Tibbs\ScopedLogger\Configuration\Configuration;

class ScopedLogManager extends LogManager
{
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
        $channelName = $channel ?? $this->getDefaultDriver();

        // Ensure channelName is string
        $channelNameString = is_string($channelName) ? $channelName : 'default';

        // Check if this channel should be wrapped
        if ($this->shouldWrapChannel($channel, $config)) {
            return new ScopedLogger($logger, $config, $channelNameString);
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
     * Check if a channel should be wrapped with ScopedLogger
     */
    protected function shouldWrapChannel(?string $channel, Configuration $config): bool
    {
        // If scoped logger is disabled globally, don't wrap
        if (! $config->isEnabled()) {
            return false;
        }

        $channel = $channel ?? $this->getDefaultDriver();

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
