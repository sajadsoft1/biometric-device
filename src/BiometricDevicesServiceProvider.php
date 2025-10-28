<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices;

use Illuminate\Support\ServiceProvider;
use Sajadsoft\BiometricDevices\Console\Commands\StartBiometricServerCommand;

/**
 * Service Provider for Biometric Devices Package
 */
class BiometricDevicesServiceProvider extends ServiceProvider
{
    /** Register services */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/biometric-devices.php',
            'biometric-devices'
        );

        // Bind BiometricDeviceManager to container
        $this->app->singleton('biometric-device', function ($app) {
            return new BiometricDeviceManager($app);
        });

        // Bind DataMapper
        $this->app->singleton(Contracts\DataMapperInterface::class, function ($app) {
            $driver      = config('biometric-devices.default_driver', 'websocket');
            $mapperClass = config("biometric-devices.mappers.aiface-{$driver}");

            return new $mapperClass;
        });
    }

    /** Bootstrap services */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/biometric-devices.php' => config_path('biometric-devices.php'),
        ], 'biometric-devices-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'biometric-devices-migrations');

        // Load migrations (auto-run when migrate command is executed)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartBiometricServerCommand::class,
            ]);
        }
    }
}
