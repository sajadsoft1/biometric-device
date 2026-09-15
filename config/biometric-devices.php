<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default communication driver to use for devices.
    | Supported: "websocket", "tcp", "http", "mqtt"
    |.
    */

    'default_driver' => env('BIOMETRIC_DRIVER', 'websocket'),

    /*
    |--------------------------------------------------------------------------
    | Default Device Type
    |--------------------------------------------------------------------------
    |
    | The default device type/model to use.
    | Supported: "aiface", "zkteco"
    |.
    */

    'default_device_type' => env('BIOMETRIC_DEVICE_TYPE', 'aiface'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    */

    'websocket' => [
        'host' => env('BIOMETRIC_WS_HOST', '0.0.0.0'),
        'port' => env('BIOMETRIC_WS_PORT', 7788),
        'backlog' => env('BIOMETRIC_WS_BACKLOG', 128),
        'read_chunk_bytes' => env('BIOMETRIC_WS_READ_CHUNK_BYTES', 64 * 1024),
        'max_handshake_bytes' => env('BIOMETRIC_WS_MAX_HANDSHAKE_BYTES', 16 * 1024),
        'max_payload_bytes' => env('BIOMETRIC_WS_MAX_PAYLOAD_BYTES', 4 * 1024 * 1024),
        'max_buffer_bytes' => env('BIOMETRIC_WS_MAX_BUFFER_BYTES', 8 * 1024 * 1024),
        'write_timeout_ms' => env('BIOMETRIC_WS_WRITE_TIMEOUT_MS', 1000),
        'slow_handler_threshold_ms' => env('BIOMETRIC_WS_SLOW_HANDLER_THRESHOLD_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | TCP Configuration
    |--------------------------------------------------------------------------
    */

    'tcp' => [
        'port' => env('BIOMETRIC_TCP_PORT', 4370),
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

    'mappers' => [
        'aiface-websocket' => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class,
        'aiface-tcp' => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class, // Same for now
        'zkteco-websocket' => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class, // Compatible with AIFace
        'zkteco-tcp' => Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper::class, // Compatible with AIFace
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
    | Custom Command Handlers
    |--------------------------------------------------------------------------
    |
    | Replace a handler for any supported device command in your application.
    | The array key is the exact command name sent by the device. Every custom
    | class must implement CommandHandlerInterface.
    |
    */

    'handlers' => [
        // 'reg' => \App\Biometric\Handlers\RegisterDeviceHandler::class,
        // 'sendlog' => \App\Biometric\Handlers\StoreAttendanceHandler::class,
        // 'sendqrcode' => \App\Biometric\Handlers\AuthorizeQrCodeHandler::class,
        // 'senduser' => \App\Biometric\Handlers\SyncUserHandler::class,
        // 'getuserlist' => \App\Biometric\Handlers\GetUserListHandler::class,
        // 'getuserinfo' => \App\Biometric\Handlers\GetUserInfoHandler::class,
        // 'checkregstatus' => \App\Biometric\Handlers\RegistrationStatusHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('BIOMETRIC_LOG_ENABLED', false),
        'channel' => env('BIOMETRIC_LOG_CHANNEL', 'daily'),
        'level' => env('BIOMETRIC_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Queue
    |--------------------------------------------------------------------------
    |
    | Enable if you want commands to be queued before sending
    |.
    */

    'queue' => [
        'enabled' => env('BIOMETRIC_QUEUE_ENABLED', false),
        'connection' => env('BIOMETRIC_QUEUE_CONNECTION', 'redis'),
        'queue' => env('BIOMETRIC_QUEUE_NAME', 'biometric-commands'),
    ],

    'retry' => [
        'max_attempts' => env('BIOMETRIC_COMMAND_MAX_ATTEMPTS', 3),
        'response_timeout' => env('BIOMETRIC_COMMAND_RESPONSE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Periodic Tasks
    |--------------------------------------------------------------------------
    */

    'tasks' => [
        'ping_interval' => env('BIOMETRIC_WS_PING_INTERVAL', 15),
        'ping_timeout' => env('BIOMETRIC_WS_PING_TIMEOUT', 60),
        'registration_timeout' => env('BIOMETRIC_WS_REGISTRATION_TIMEOUT', 30),
        'command_check_interval' => env('BIOMETRIC_COMMAND_CHECK_INTERVAL', 1),
        'command_batch_size' => env('BIOMETRIC_COMMAND_BATCH_SIZE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override default models if needed
    |
    */

    'models' => [
        // Override these models in your application if needed
        'device' => Sajadsoft\BiometricDevices\Models\Device::class,
        'device_command' => Sajadsoft\BiometricDevices\Models\DeviceCommand::class,
    ],
];
