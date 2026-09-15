<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    /**
     * Log a debug message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->debug("Biometric: {$message}", self::sanitize($context));
    }

    /**
     * Log an info message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->info("Biometric: {$message}", self::sanitize($context));
    }

    /**
     * Log a warning message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->warning("Biometric: {$message}", self::sanitize($context));
    }

    /**
     * Log an error message if logging is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        Log::channel(self::getChannel())->error("Biometric: {$message}", self::sanitize($context));
    }

    /**
     * Remove large and sensitive biometric values while retaining diagnostic metadata.
     *
     * @param  array<string|int, mixed>  $context
     * @return array<string|int, mixed>
     */
    public static function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = self::sanitizeValue($value, is_string($key) ? strtolower($key) : null);
        }

        return $sanitized;
    }

    private static function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        $sensitiveKeys = [
            'record',
            'image',
            'photo',
            'fingerprint',
            'biometric_data',
            'secret',
            'password',
            'pwd',
        ];

        if (in_array($key, $sensitiveKeys, true)) {
            if (is_string($value)) {
                return [
                    'redacted' => true,
                    'length' => strlen($value),
                    'sha256' => hash('sha256', $value),
                ];
            }

            return [
                'redacted' => true,
                'type' => get_debug_type($value),
                'count' => is_countable($value) ? count($value) : null,
            ];
        }

        if (is_array($value)) {
            return self::sanitize($value);
        }

        if (is_string($value) && strlen($value) > 2048) {
            return [
                'redacted' => true,
                'length' => strlen($value),
                'sha256' => hash('sha256', $value),
            ];
        }

        if (is_object($value)) {
            return ['object' => $value::class];
        }

        if (is_resource($value)) {
            return ['resource' => get_resource_type($value)];
        }

        return $value;
    }

    /** Check if logging is enabled in the config. */
    private static function isEnabled(): bool
    {
        return (bool) config('biometric-devices.logging.enabled', false);
    }

    /** Get the configured log channel. */
    private static function getChannel(): string
    {
        return (string) config('biometric-devices.logging.channel', 'daily');
    }
}
