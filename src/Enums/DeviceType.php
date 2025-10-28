<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum DeviceType: string
{
    case WEBSOCKET = 'websocket';
    case TCP       = 'tcp';
    case HTTP      = 'http';
    case MQTT      = 'mqtt';

    /** Get the communication driver class for this device type */
    public function getDriverClass(): string
    {
        return match ($this) {
            self::WEBSOCKET => \Sajadsoft\BiometricDevices\Services\DeviceDrivers\WebSocketDeviceDriver::class,
            self::TCP       => \Sajadsoft\BiometricDevices\Services\DeviceDrivers\TcpDeviceDriver::class,
            self::HTTP      => \Sajadsoft\BiometricDevices\Services\DeviceDrivers\HttpDeviceDriver::class,
            self::MQTT      => \Sajadsoft\BiometricDevices\Services\DeviceDrivers\MqttDeviceDriver::class,
        };
    }

    /** Get display label */
    public function label(): string
    {
        return match ($this) {
            self::WEBSOCKET => 'WebSocket Protocol',
            self::TCP       => 'TCP/IP Protocol',
            self::HTTP      => 'HTTP Protocol',
            self::MQTT      => 'MQTT Protocol',
        };
    }
}
