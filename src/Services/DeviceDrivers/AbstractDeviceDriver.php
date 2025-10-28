<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\DeviceDrivers;

use Illuminate\Console\Command;
use Sajadsoft\BiometricDevices\Contracts\DeviceDriverInterface;
use Sajadsoft\BiometricDevices\Services\Pipelines\MessagePipeline;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * Abstract base class for device drivers
 */
abstract class AbstractDeviceDriver implements DeviceDriverInterface
{
    protected ?Command $console = null;

    protected MessagePipeline $pipeline;

    protected array $connectedDevices = [];

    public function __construct()
    {
        $this->pipeline = new MessagePipeline;
    }

    /** Set console output */
    public function setConsole(Command $console): void
    {
        $this->console = $console;
    }

    /** Output info message */
    protected function info(string $message): void
    {
        // نمایش در console (برای دیدن در ترمینال)
        $this->console?->info($message);

        // لاگ فقط اگر console نداشتیم (مثلاً در background)
        if (! $this->console) {
            Logger::info($message);
        }
    }

    /** Output warning message */
    protected function warn(string $message): void
    {
        // نمایش در console (برای دیدن در ترمینال)
        $this->console?->warn($message);

        // لاگ فقط اگر console نداشتیم یا برای warning ها
        if (! $this->console) {
            Logger::warning($message);
        }
    }

    /** Output error message */
    protected function error(string $message): void
    {
        // نمایش در console (برای دیدن در ترمینال)
        $this->console?->error($message);

        // Error ها همیشه لاگ می‌شن
        Logger::error($message);
    }

    /** Output debug message (logs only, doesn't show in console) */
    protected function debug(string $message): void
    {
        // Debug messages فقط در log ثبت می‌شوند (تا console شلوغ نشود)
        Logger::debug($message);
    }

    /** Process message through pipeline */
    protected function processMessage(array $data, $connection, ?string $deviceSerial = null): ?array
    {
        $context = [
            'data' => $data,
            'connection' => $connection,
            'device_serial' => $deviceSerial,
        ];

        // پردازش از طریق pipeline
        return $this->pipeline->process($context, function ($context) {
            $handler = $context['handler'] ?? null;

            if (! $handler) {
                return;
            }

            // اجرای handler
            return $handler->handle(
                $context['normalized_data'],
                $context['connection']
            );
        });
    }

    /** Get connected devices */
    public function getConnectedDevices(): array
    {
        return array_values($this->connectedDevices);
    }

    /** Check if device is connected by serial number */
    public function isDeviceConnected(string $deviceSerial): bool
    {
        return in_array($deviceSerial, $this->connectedDevices, true);
    }
}
