<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Sajadsoft\BiometricDevices\Services\DeviceDrivers\WebSocketDeviceDriver;

/**
 * Start biometric device communication server
 */
class StartBiometricServerCommand extends Command
{
    protected $signature = 'biometric:start-server {--driver=websocket} {--port=7788} {--host=0.0.0.0}';

    protected $description = 'Start biometric device communication server';

    public function handle(): int
    {
        $driver = $this->option('driver');
        $host   = $this->option('host');
        $port   = $this->option('port');

        $this->info('Starting biometric server...');
        $this->info("Driver: {$driver}");
        $this->info("Host: {$host}:{$port}");

        // فعلاً فقط WebSocket
        if ($driver === 'websocket') {
            $driverInstance = app(WebSocketDeviceDriver::class);
            $driverInstance->setConsole($this);

            try {
                $driverInstance->start($host, (int) $port);
            } catch (Exception $e) {
                $this->error("Failed to start server: {$e->getMessage()}");

                return self::FAILURE;
            }
        } else {
            $this->error("Driver '{$driver}' not implemented yet");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
