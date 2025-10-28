<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\DeviceDrivers;

use Exception;
use JsonException;
use RuntimeException;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * WebSocket driver for biometric devices
 */
class WebSocketDeviceDriver extends AbstractDeviceDriver
{
    protected $socket;

    protected array $sockets = [];

    protected array $handshakeComplete = [];

    protected array $socketToSerial = []; // Map socket ID => serial number

    protected float $lastPingTime;

    protected float $lastCommandCheckTime;

    /** Start WebSocket server */
    public function start(string $host, int $port): void
    {
        $this->info("Starting WebSocket server on {$host}:{$port}...");

        // ایجاد socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ( ! $this->socket) {
            throw new RuntimeException('Failed to create socket');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if ( ! socket_bind($this->socket, $host, $port)) {
            throw new RuntimeException("Failed to bind to {$host}:{$port}");
        }

        if ( ! socket_listen($this->socket, 5)) {
            throw new RuntimeException('Failed to listen on socket');
        }

        $this->sockets[]            = $this->socket;
        $this->lastPingTime         = microtime(true);
        $this->lastCommandCheckTime = microtime(true);

        $this->info('WebSocket server started successfully!');
        $this->info('Waiting for device connections...');

        // حلقه اصلی
        $this->mainLoop();
    }

    /** Main server loop */
    protected function mainLoop(): void
    {
        while (true) {
            $read   = $this->sockets;
            $write  = null;
            $except = null;

            if (socket_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $sock) {
                    if ($sock === $this->socket) {
                        $this->handleNewConnection();
                    } else {
                        $this->handleSocketActivity($sock);
                    }
                }
            }

            $this->performPeriodicTasks();
        }
    }

    /** Handle new connection */
    protected function handleNewConnection(): void
    {
        $clientSocket = socket_accept($this->socket);

        if ( ! $clientSocket) {
            return;
        }

        socket_getpeername($clientSocket, $address, $port);
        $socketId = $this->getSocketId($clientSocket);

        $this->info("New connection from {$address}:{$port}");

        $this->sockets[]                    = $clientSocket;
        $this->handshakeComplete[$socketId] = false;
    }

    /**
     * Handle socket activity (data received)
     *
     * @throws JsonException
     */
    protected function handleSocketActivity($socket): void
    {
        $frame = @socket_read($socket, 4096);

        if ( ! $frame) {
            $this->handleDisconnection($socket);

            return;
        }

        $socketId = $this->getSocketId($socket);

        // WebSocket handshake
        if ( ! ($this->handshakeComplete[$socketId] ?? false)) {
            if ($this->performHandshake($socket, $frame)) {
                $this->handshakeComplete[$socketId] = true;
                socket_getpeername($socket, $address, $port);
                $this->info("WebSocket handshake completed for {$address}:{$port}");
            }

            return;
        }

        // Decode frame
        $payload = $this->decodeFrame($frame);

        if ($payload === null) {
            return;
        }

        // Handle ping
        if (is_array($payload) && isset($payload['_ping'])) {
            $this->sendPong($socket);

            return;
        }

        // Clean payload from control characters
        $payload = $this->cleanPayload($payload);

        // Parse JSON
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->warn("Invalid JSON from socket {$socketId}: {$e->getMessage()}");
            $this->debug('Payload: ' . substr($payload, 0, 200));

            return;
        }

        if ( ! $data) {
            $this->warn("Invalid JSON from socket {$socketId}");

            return;
        }

        // پردازش از طریق pipeline
        $deviceSerial = $this->socketToSerial[$socketId] ?? null;
        $response     = $this->processMessage($data, $socket, $deviceSerial);

        // ذخیره serial number برای این socket
        if (isset($data['sn'])) {
            $this->socketToSerial[$socketId]   = $data['sn'];
            $this->connectedDevices[$socketId] = $data['sn'];
        }

        // ارسال پاسخ
        if ($response) {
            $this->sendMessage($socket, $response);
        }
    }

    /** Perform WebSocket handshake */
    protected function performHandshake($socket, $buffer): bool
    {
        if ( ! str_contains($buffer, 'Sec-WebSocket-Key:')) {
            return false;
        }

        $keyStart = strpos($buffer, 'Sec-WebSocket-Key:') + 18;
        $keyEnd   = strpos($buffer, "\r\n", $keyStart);
        $key      = trim(substr($buffer, $keyStart, $keyEnd - $keyStart));

        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Sec-WebSocket-Version: 13\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

        socket_write($socket, $response, strlen($response));

        return true;
    }

    /** Decode WebSocket frame */
    protected function decodeFrame($frame): mixed
    {
        $opcode = ord($frame[0]) & 15;

        if ($opcode == 9) {
            return ['_ping' => true];
        }

        if ($opcode != 1) {
            return null;
        }

        $b2            = ord($frame[1]);
        $mask          = ($b2 & 128) != 0;
        $payloadLength = $b2 & 127;
        $dataPos       = 2;

        if ($payloadLength === 126) {
            $payloadLength = ord($frame[3]) + (ord($frame[2]) << 8);
            $dataPos       = 4;
        } elseif ($payloadLength > 126) {
            $payloadLength = ord($frame[9]) + (ord($frame[8]) << 8) + (ord($frame[7]) << 16) + (ord($frame[6]) << 24);
            $dataPos       = 10;
        }

        $packet = '';
        if ($mask) {
            $maskKey = substr($frame, $dataPos, 4);
            $dataPos += 4;

            $frameLength   = strlen($frame);
            $availableData = $frameLength - $dataPos;
            $actualLength  = min($payloadLength, $availableData);

            for ($i = 0; $i < $actualLength; $i++) {
                if (isset($frame[$i + $dataPos])) {
                    $packet .= $frame[$i + $dataPos] ^ $maskKey[$i % 4];
                }
            }
        } else {
            $packet = substr($frame, $dataPos, $payloadLength);
        }

        return $packet;
    }

    /** Send the message to device */
    public function sendMessage($socket, array $message): void
    {
        $json      = json_encode($message);
        $length    = strlen($json);
        $firstByte = 0x81; // Text frame

        if ($length <= 125) {
            $header = pack('CC', $firstByte, $length);
        } elseif ($length <= 65535) {
            $header = pack('CCn', $firstByte, 126, $length);
        } else {
            $header = pack('CCNN', $firstByte, 127, 0, $length);
        }

        $frame = $header . $json;
        socket_write($socket, $frame, strlen($frame));
    }

    /** Find socket by device serial */
    protected function findSocketBySerial(string $serial): mixed
    {
        $socketId = array_search($serial, $this->socketToSerial);

        if ($socketId === false) {
            return null;
        }

        foreach ($this->sockets as $socket) {
            if ($this->getSocketId($socket) == $socketId) {
                return $socket;
            }
        }

        return null;
    }

    /** Get unique socket ID */
    protected function getSocketId($socket): string
    {
        $address = 'unknown';
        $port    = 0;

        try {
            @socket_getpeername($socket, $address, $port);
        } catch (Exception $e) {
            // اگر نتونست peer name بگیره، از resource ID استفاده می‌کنیم
            return 'socket_' . (int) $socket;
        }

        return "{$address}_{$port}";
    }

    /** Send pong */
    protected function sendPong($socket): void
    {
        $pong = chr(0x8A) . chr(0x00);
        socket_write($socket, $pong, strlen($pong));
    }

    /** Clean payload from control characters */
    protected function cleanPayload(string $payload): string
    {
        // Remove null bytes and control characters (except tab, newline, carriage return)
        $payload = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $payload);

        // Remove UTF-8 BOM if present
        $payload = str_replace("\xEF\xBB\xBF", '', $payload);

        // Trim whitespace
        return trim($payload);
    }

    /** Periodic tasks */
    protected function performPeriodicTasks(): void
    {
        $now = microtime(true);

        // Ping every 15 seconds
        if ($now - $this->lastPingTime > 15) {
            $this->sendPingToAll();
            $this->lastPingTime = $now;
        }

        // Check commands every 1 second
        if ($now - $this->lastCommandCheckTime > 1) {
            $this->checkPendingCommands();
            $this->lastCommandCheckTime = $now;
        }
    }

    /** Send ping to all connected devices */
    protected function sendPingToAll(): void
    {
        $ping = chr(0x89) . chr(0x00);

        foreach ($this->sockets as $socket) {
            if ($socket === $this->socket) {
                continue;
            }

            $socketId = $this->getSocketId($socket);

            if ($this->handshakeComplete[$socketId] ?? false) {
                @socket_write($socket, $ping, strlen($ping));
            }
        }
    }

    /** Check for pending commands and send them */
    protected function checkPendingCommands(): void
    {
        $commandModel = config('biometric-devices.models.device_command');

        // بررسی وجود مدل DeviceCommand
        if ( ! class_exists($commandModel)) {
            return;
        }

        // دریافت دستورات pending از دیتابیس
        $commands = $commandModel::query()
            ->with('device:id,serial')
            ->where('status', \Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum::PENDING)
            ->where('send_status', false)
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($commands as $command) {
            $this->sendCommandFromDatabase($command);
        }
    }

    /** Send command from database record */
    protected function sendCommandFromDatabase($command): void
    {
        $device = $command->device;

        if ( ! $device) {
            return;
        }

        // بررسی تعداد retry ها
        if ($command->hasExceededMaxRetries()) {
            $command->markAsFailed('Device not connected after maximum retry attempts');
            Logger::warning("Command failed after max retries: {$device->serial}", [
                'command_id'   => $command->id,
                'command_name' => $command->command_name,
                'retry_count'  => $command->error_count,
                'max_attempts' => config('biometric-devices.retry.max_attempts', 3),
            ]);

            return;
        }

        // آماده‌سازی params
        $params = json_decode($command->command_content, true) ?? [];

        // ارسال
        $sent = $this->sendRawCommand(
            $device->serial,
            $command->command_name,
            $params
        );

        if ($sent) {
            $command->markAsSent();
        } else {
            // افزایش تعداد retry
            $command->incrementRetryCount();
        }
    }

    /** Handle disconnection */
    protected function handleDisconnection($socket): void
    {
        $socketId = $this->getSocketId($socket);
        $this->info("Client disconnected: {$socketId}");

        // بروزرسانی وضعیت در دیتابیس و پخش Event
        if (isset($this->socketToSerial[$socketId])) {
            $serialNum = $this->socketToSerial[$socketId];

            // ذخیره وضعیت آفلاین در دیتابیس
            $this->markDeviceAsOffline($serialNum);

            // پخش Event - برای اطلاع‌رسانی و پردازش‌های اضافی
            event(new \Sajadsoft\BiometricDevices\Events\DeviceDisconnected(
                $serialNum,
                now()
            ));

            $this->info("Device {$serialNum} disconnected");

            unset($this->socketToSerial[$socketId], $this->connectedDevices[$socketId]);
        }

        socket_close($socket);

        // حذف از لیست sockets
        $this->sockets = array_filter($this->sockets, fn ($s) => $s !== $socket);
        unset($this->handshakeComplete[$socketId]);
    }

    /** علامت‌گذاری دستگاه به عنوان آفلاین */
    protected function markDeviceAsOffline(string $serial): void
    {
        $deviceModel = config('biometric-devices.models.device');

        if ( ! class_exists($deviceModel)) {
            return;
        }

        $device = $deviceModel::where('serial', $serial)->first();

        if ($device) {
            $device->markAsOffline();
            $this->info("Device {$serial} marked as offline in database");
        }
    }

    /** Stop server */
    public function stop(): void
    {
        foreach ($this->sockets as $socket) {
            if ($socket !== $this->socket) {
                socket_close($socket);
            }
        }

        if ($this->socket) {
            socket_close($this->socket);
        }

        $this->info('WebSocket server stopped');
    }

    // ============================================
    // Implementation of DeviceDriverInterface
    // ============================================

    // ============================================
    // User Management
    // ============================================

    /** Send add user command */
    public function sendAddUser(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapAddUserCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setuserinfo', $command);
    }

    /** Send delete user command */
    public function sendDeleteUser(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapDeleteUserCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'deleteuser', $command);
    }

    /** Send get user list command */
    public function sendGetUserList(string $deviceSerial, bool $startFromBeginning = true): bool
    {
        $command = [
            'cmd' => 'getuserlist',
            'stn' => $startFromBeginning ? 1 : 0,
        ];

        return $this->sendRawCommand($deviceSerial, 'getuserlist', $command);
    }

    /** Send get user info command */
    public function sendGetUserInfo(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\GetUserInfoDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapGetUserInfoCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'getuserinfo', $command);
    }

    // ============================================
    // Device Control
    // ============================================

    /** Send open door command */
    public function sendOpenDoor(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapOpenDoorCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'opendoor', $command);
    }

    /** Send get device info command */
    public function sendGetDeviceInfo(string $deviceSerial): bool
    {
        $command = [
            'cmd' => 'getdevinfo',
        ];

        return $this->sendRawCommand($deviceSerial, 'getdevinfo', $command);
    }

    /** Send reboot command */
    public function sendReboot(string $deviceSerial): bool
    {
        $command = [
            'cmd' => 'reboot',
        ];

        return $this->sendRawCommand($deviceSerial, 'reboot', $command);
    }

    /** Send init system command */
    public function sendInitSystem(string $deviceSerial): bool
    {
        $command = [
            'cmd' => 'initsys',
        ];

        return $this->sendRawCommand($deviceSerial, 'initsys', $command);
    }

    /** Send set time command */
    public function sendSetTime(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\SetTimeDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapSetTimeCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'settime', $command);
    }

    // ============================================
    // Access Control
    // ============================================

    /** Send set user access command */
    public function sendSetUserAccess(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapSetUserAccessCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setuserlock', $command);
    }

    /** Send set device lock command */
    public function sendSetDeviceLock(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\SetDeviceLockDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapSetDeviceLockCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setdevlock', $command);
    }

    // ============================================
    // Attendance Logs
    // ============================================

    /** Send get all logs command */
    public function sendGetAllLogs(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\GetLogsDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapGetLogsCommand($dto, 'getalllog');

        return $this->sendRawCommand($deviceSerial, 'getalllog', $command);
    }

    /** Send get new logs command */
    public function sendGetNewLogs(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\GetLogsDTO $dto): bool
    {
        $mapper  = app(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface::class);
        $command = $mapper->mapGetLogsCommand($dto, 'getnewlog');

        return $this->sendRawCommand($deviceSerial, 'getnewlog', $command);
    }

    /** Send raw command to device */
    public function sendRawCommand(string $deviceSerial, string $commandName, array $params): bool
    {
        // پیدا کردن socket دستگاه
        $socketId = null;
        foreach ($this->connectedDevices as $sid => $serial) {
            if ($serial === $deviceSerial) {
                $socketId = $sid;

                break;
            }
        }

        if ( ! $socketId) {
            Logger::debug("Device not connected: {$deviceSerial}");

            return false;
        }

        // پیدا کردن socket واقعی
        $socket = null;
        foreach ($this->sockets as $sock) {
            if ($this->getSocketId($sock) === $socketId && $sock !== $this->socket) {
                $socket = $sock;

                break;
            }
        }

        if ( ! $socket) {
            Logger::debug("Socket not found for device: {$deviceSerial}");

            return false;
        }

        // ارسال پیام با استفاده از sendMessage که خودش encode می‌کنه
        $this->sendMessage($socket, $params);

        Logger::debug("Command sent to device: {$deviceSerial}", [
            'command' => $commandName,
            'params'  => $params,
        ]);

        return true;
    }
}
