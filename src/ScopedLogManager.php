<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger;

use Illuminate\Log\LogManager;
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
