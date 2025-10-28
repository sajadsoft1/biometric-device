<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    /**
     * Log a debug message if logging is enabled.
     *
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        if ( ! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->debug("Biometric: {$message}" . '  :' . json_encode($context, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    }

    /**
     * Log an info message if logging is enabled.
     *
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        if ( ! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->info("Biometric: {$message}" . '  :' . json_encode($context, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    }

    /**
     * Log a warning message if logging is enabled.
     *
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        if ( ! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->warning("Biometric: {$message}" . '  :' . json_encode($context, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    }

    /**
     * Log an error message if logging is enabled.
     *
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        if ( ! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->error("Biometric: {$message}" . '  :' . json_encode($context, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    }

    /** Check if logging is enabled in config. */
    private static function isEnabled(): bool
    {
        return (bool) config('biometric-devices.logging.enabled', false);
    }

    /** Get the configured log channel. */
    private static function getChannel(): string
    {
        return (string) config('biometric-devices.logging.channel', 'daily');
    }

    /** Get the configured log level. */
    private static function getLevel(): string
    {
        return (string) config('biometric-devices.logging.level', 'info');
    }
}
