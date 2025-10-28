<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services;

use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\Enums\DeviceModel;

/**
 * Factory for creating appropriate data mappers based on device type
 */
class MapperFactory
{
    /** Create a mapper instance for the given device type and protocol */
    public static function create(string $deviceType, string $protocol = 'websocket'): DataMapperInterface
    {
        // Try to get enum from device type
        $deviceModel = DeviceModel::tryFrom($deviceType);

        if ($deviceModel) {
            $mapperClass = $deviceModel->getMapperClass($protocol);
        } else {
            // Fallback to direct config lookup for custom mappers
            $mapperClass = config("biometric-devices.mappers.{$deviceType}-{$protocol}")
                ?? config("biometric-devices.mappers.aiface-{$protocol}"); // Default fallback
        }

        return new $mapperClass;
    }

    /** Get mapper for default device configuration */
    public static function createDefault(): DataMapperInterface
    {
        $driver = config('biometric-devices.default_driver', 'websocket');
        $deviceType = config('biometric-devices.default_device_type', 'aiface');

        return self::create($deviceType, $driver);
    }
}
