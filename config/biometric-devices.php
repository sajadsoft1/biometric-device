<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default communication driver to use for devices.
    | Supported: "websocket", “tcp”, "http", "mqtt"
    |.
    */

    'default_driver' => env('BIOMETRIC_DRIVER', 'websocket'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    */

    'websocket'      => [
        'host' => env('BIOMETRIC_WS_HOST', '0.0.0.0'),
        'port' => env('BIOMETRIC_WS_PORT', 7788),
    ],

    /*
    |--------------------------------------------------------------------------
    | TCP Configuration
    |--------------------------------------------------------------------------
    */

    'tcp'            => [
        'port'    => env('BIOMETRIC_TCP_PORT', 4370),
        'timeout' => env('BIOMETRIC_TCP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Mappers
    |--------------------------------------------------------------------------
    |
    | Data mappers convert device-specific formats to standardized DTOs
    |
    */

    'mappers'        => [
        'aiface-websocket' => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class,
        'aiface-tcp'       => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class, // Same for now
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Mappers
    |--------------------------------------------------------------------------
    |
    | You can add your own custom mappers for different device brands
    |.
    */

    'custom_mappers' => [
        // 'my-device' => \App\Mappers\MyDeviceMapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging'        => [
        'enabled' => env('BIOMETRIC_LOG_ENABLED', false),
        'channel' => env('BIOMETRIC_LOG_CHANNEL', 'daily'),
        'level'   => env('BIOMETRIC_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Queue
    |--------------------------------------------------------------------------
    |
    | Enable if you want commands to be queued before sending
    |.
    */

    'queue'          => [
        'enabled'    => env('BIOMETRIC_QUEUE_ENABLED', false),
        'connection' => env('BIOMETRIC_QUEUE_CONNECTION', 'redis'),
        'queue'      => env('BIOMETRIC_QUEUE_NAME', 'biometric-commands'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Periodic Tasks
    |--------------------------------------------------------------------------
    */

    'tasks'          => [
        'ping_interval'          => 15, // seconds
        'command_check_interval' => 1, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override default models if needed
    |
    */

    'models'         => [
        // Override these models in your application if needed
        'device'         => Sajadsoft\BiometricDevices\Models\Device::class,
        'device_command' => Sajadsoft\BiometricDevices\Models\DeviceCommand::class,
    ],
];
