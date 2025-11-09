<?php

declare(strict_types=1);

namespace Tibbs\ScopedLogger\Exceptions;

use InvalidArgumentException;

class InvalidScopeConfigurationException extends InvalidArgumentException
{
    public static function invalidLevel(string $level, string $configKey): self
    {
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        return new self(
            "Invalid log level '{$level}' for config key '{$configKey}'. ".
            'Valid levels are: '.implode(', ', $validLevels).'. '.
            "You can also use 'false' to suppress a scope completely."
        );
    }

    public static function invalidPattern(string $pattern, string $reason): self
    {
        return new self(
            "Invalid scope pattern '{$pattern}': {$reason}. ".
            'Patterns support wildcards: * (multiple chars) and ? (single char). '.
            "Examples: 'App\\Services\\*', 'payment.?'"
        );
    }

    public static function emptyScope(): self
    {
        return new self(
            'Scope identifier cannot be empty. '.
            'Provide a valid scope name or class FQCN.'
        );
    }

    public static function invalidUnknownScopeHandling(string $value): self
    {
        $validValues = ['exception', 'log', 'ignore'];

        return new self(
            "Invalid unknown_scope_handling value '{$value}'. ".
            'Valid values are: '.implode(', ', $validValues)
        );
    }

    public static function invalidConfigType(string $message): self
    {
        return new self(
            "Invalid configuration type: {$message}. ".
            'Check your config/scoped-logger.php file for type mismatches.'
        );
    }
}
