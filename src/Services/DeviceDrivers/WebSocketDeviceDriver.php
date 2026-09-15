<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\DeviceDrivers;

use JsonException;
use RuntimeException;
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetLogsDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetUserInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenLockDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetDeviceLockDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetTimeDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Events\DeviceDisconnected;
use Sajadsoft\BiometricDevices\Support\Logger;
use Throwable;

/**
 * WebSocket driver for biometric devices
 */
class WebSocketDeviceDriver extends AbstractDeviceDriver
{
    protected mixed $socket;

    protected array $sockets = [];

    protected array $handshakeComplete = [];

    protected array $socketToSerial = []; // Map socket ID => serial number

    protected array $messageBuffer = []; // Buffer for incomplete messages/frames

    protected array $fragmentedMessages = []; // Buffer WebSocket-level fragmented messages

    protected array $fragmentedOpcodes = []; // Original opcode for fragmented messages

    protected array $socketIds = []; // Cached peer identifier by Socket object ID

    protected float $lastPingTime;

    protected float $lastCommandCheckTime;

    protected array $lastActivityTime = []; // Track last activity time for each socket

    protected array $awaitingPong = []; // Track sockets waiting for pong response

    protected bool $running = false;

    /** Start WebSocket server */
    public function start(string $host, int $port): void
    {
        $this->info("Starting WebSocket server on {$host}:{$port}...");

        // ایجاد socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (! $this->socket) {
            throw new RuntimeException('Failed to create socket');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (! socket_bind($this->socket, $host, $port)) {
            throw new RuntimeException("Failed to bind to {$host}:{$port}");
        }

        if (! socket_listen($this->socket, max(5, (int) config('biometric-devices.websocket.backlog', 128)))) {
            throw new RuntimeException('Failed to listen on socket');
        }

        $this->sockets[] = $this->socket;
        $this->lastPingTime = microtime(true);
        $this->lastCommandCheckTime = microtime(true);
        $this->running = true;

        $this->info('WebSocket server started successfully!');
        $this->info('Waiting for device connections...');

        // حلقه اصلی
        $this->mainLoop();
    }

    /** Main server loop */
    protected function mainLoop(): void
    {
        while ($this->running) {
            $read = $this->sockets;
            $write = null;
            $except = null;

            $selected = @socket_select($read, $write, $except, 0, 100000);

            if ($selected === false) {
                $errorCode = socket_last_error($this->socket);
                $this->error("Socket select failed: code={$errorCode}, message=".socket_strerror($errorCode));
                socket_clear_error($this->socket);
                usleep(100000);

                continue;
            }

            if ($selected > 0) {
                foreach ($read as $sock) {
                    try {
                        if ($sock === $this->socket) {
                            $this->handleNewConnection();
                        } else {
                            $this->handleSocketActivity($sock);
                        }
                    } catch (Throwable $exception) {
                        $socketId = $sock === $this->socket ? 'server' : $this->getSocketId($sock);
                        $this->error("Unhandled socket error for {$socketId}: {$exception->getMessage()}");
                    }
                }
            }

            try {
                $this->performPeriodicTasks();
            } catch (Throwable $exception) {
                $this->error("Periodic task failed: {$exception->getMessage()}");
            }
        }
    }

    /** Handle new connection */
    protected function handleNewConnection(): void
    {
        $clientSocket = socket_accept($this->socket);

        if (! $clientSocket) {
            return;
        }

        // تنظیم socket به حالت non-blocking برای جلوگیری از block شدن در socket_read
        socket_set_nonblock($clientSocket);

        socket_getpeername($clientSocket, $address, $port);
        $socketId = $this->getSocketId($clientSocket);

        $this->info("New connection from {$address}:{$port}");

        $this->sockets[] = $clientSocket;
        $this->handshakeComplete[$socketId] = false;
        $this->lastActivityTime[$socketId] = microtime(true);
    }

    /** Handle socket activity (data received). */
    protected function handleSocketActivity($socket): void
    {
        socket_clear_error($socket);

        $frame = @socket_read($socket, $this->readChunkBytes());

        if ($frame === false) {
            $errorCode = socket_last_error($socket);

            if ($this->isTransientSocketError($errorCode)) {
                socket_clear_error($socket);

                return;
            }

            $socketId = $this->getSocketId($socket);
            $this->warn("Socket read failed: socket={$socketId}, code={$errorCode}, message=".socket_strerror($errorCode));
            $this->handleDisconnection($socket, 'read_error', $errorCode);

            return;
        }

        if ($frame === '') {
            $this->handleDisconnection($socket, 'peer_closed');

            return;
        }

        $socketId = $this->getSocketId($socket);
        $this->lastActivityTime[$socketId] = microtime(true);

        if (! ($this->handshakeComplete[$socketId] ?? false)) {
            $handshakeBuffer = ($this->messageBuffer[$socketId] ?? '').$frame;
            $headerEnd = strpos($handshakeBuffer, "\r\n\r\n");

            if ($headerEnd === false) {
                if (strlen($handshakeBuffer) > $this->maxHandshakeBytes()) {
                    $this->warn("WebSocket handshake exceeded maximum size for {$socketId}");
                    $this->handleDisconnection($socket, 'handshake_too_large');

                    return;
                }

                $this->messageBuffer[$socketId] = $handshakeBuffer;

                return;
            }

            $httpHeaderLength = $headerEnd + 4;
            $httpHeader = substr($handshakeBuffer, 0, $httpHeaderLength);
            $remainingData = substr($handshakeBuffer, $httpHeaderLength);

            if ($this->performHandshake($socket, $httpHeader)) {
                $this->handshakeComplete[$socketId] = true;
                $this->messageBuffer[$socketId] = '';
                socket_getpeername($socket, $address, $port);
                $this->info("WebSocket handshake completed for {$address}:{$port}");
            } else {
                $this->handleDisconnection($socket, 'invalid_handshake');

                return;
            }

            if ($remainingData === '') {
                return;
            }

            $frame = $remainingData;
        }

        $dataRec = $this->messageBuffer[$socketId] ?? '';
        $dataRec .= $frame;
        $dataLen = strlen($dataRec);

        while ($dataLen >= 2) {
            $fin = (ord($dataRec[0]) & 0x80) !== 0;
            $opcode = ord($dataRec[0]) & 15;
            $b2 = ord($dataRec[1]);
            $mask = ($b2 & 128) !== 0;
            $payloadLength = $b2 & 127;
            $headerLength = 2;

            if ($payloadLength === 126) {
                $headerLength += 2;
            } elseif ($payloadLength === 127) {
                $headerLength += 8;
            }

            if ($dataLen < $headerLength) {
                break;
            }

            $nDataPos = 2;

            if ($payloadLength === 126) {
                $unpackedLength = unpack('nlength', substr($dataRec, $nDataPos, 2));
                $payloadLength = $unpackedLength['length'];
                $nDataPos += 2;
            } elseif ($payloadLength === 127) {
                $unpackedLength = unpack('Nhigh/Nlow', substr($dataRec, $nDataPos, 8));

                if ($unpackedLength['high'] !== 0) {
                    $this->warn("WebSocket payload is too large for {$socketId}");
                    $this->handleDisconnection($socket, 'payload_too_large');

                    return;
                }

                $payloadLength = $unpackedLength['low'];
                $nDataPos += 8;
            }

            if ($payloadLength > $this->maxPayloadBytes()) {
                $this->warn("Invalid payload length: {$payloadLength} from {$socketId}");
                $this->handleDisconnection($socket, 'payload_too_large');

                return;
            }

            $totalFrameLength = $headerLength + ($mask ? 4 : 0) + $payloadLength;

            if ($dataLen < $totalFrameLength) {
                break;
            }

            if ($mask) {
                $maskPos = $nDataPos;
                $nDataPos += 4;
                $maskingKey = substr($dataRec, $maskPos, 4);
                $maskedPayload = substr($dataRec, $nDataPos, $payloadLength);
                $repeatedMask = substr(str_repeat($maskingKey, (int) ceil($payloadLength / 4)), 0, $payloadLength);
                $packet = $maskedPayload ^ $repeatedMask;
            } else {
                $packet = substr($dataRec, $nDataPos, $payloadLength);
            }

            try {
                $this->processFrame($packet, $socket, $socketId, $opcode, $fin);
            } catch (Throwable $exception) {
                $this->error("Failed to process WebSocket frame for {$socketId}: {$exception->getMessage()}");
            }

            $dataRec = substr($dataRec, $totalFrameLength);
            $dataLen = strlen($dataRec);
        }

        if ($dataLen > $this->maxBufferBytes()) {
            $this->warn("WebSocket buffer exceeded maximum size for {$socketId}");
            $this->handleDisconnection($socket, 'buffer_too_large');

            return;
        }

        $this->messageBuffer[$socketId] = $dataRec;
    }

    /** Process a complete WebSocket frame
     * @throws JsonException
     */
    protected function processFrame(string $packet, $socket, string $socketId, int $opcode, bool $fin = true): void
    {
        $this->lastActivityTime[$socketId] = microtime(true);
        unset($this->awaitingPong[$socketId]);

        if ($opcode >= 8 && (! $fin || strlen($packet) > 125)) {
            $this->warn("Invalid WebSocket control frame from {$socketId}");
            $this->handleDisconnection($socket, 'invalid_control_frame');

            return;
        }

        // Handle close frame (opcode 0x8)
        if ($opcode === 8) {
            // Send close frame back to gracefully close connection
            $this->sendCloseFrame($socket);
            $this->handleDisconnection($socket);

            return;
        }

        // Handle pong response (opcode 0xA)
        if ($opcode === 10) {
            // Device responded to our ping
            unset($this->awaitingPong[$socketId]);

            return;
        }

        // Handle ping from device
        if ($opcode === 9) {
            $this->sendPong($socket, $packet);

            return;
        }

        if ($opcode === 0) {
            if (! isset($this->fragmentedMessages[$socketId], $this->fragmentedOpcodes[$socketId])) {
                $this->warn("Unexpected continuation frame from {$socketId}");

                return;
            }

            $this->fragmentedMessages[$socketId] .= $packet;

            if (strlen($this->fragmentedMessages[$socketId]) > $this->maxPayloadBytes()) {
                $this->handleDisconnection($socket, 'fragmented_payload_too_large');

                return;
            }

            if (! $fin) {
                return;
            }

            $packet = $this->fragmentedMessages[$socketId];
            $opcode = $this->fragmentedOpcodes[$socketId];
            unset($this->fragmentedMessages[$socketId], $this->fragmentedOpcodes[$socketId]);
        } elseif (! $fin) {
            if ($opcode !== 1 && $opcode !== 2) {
                $this->warn("Unsupported fragmented opcode {$opcode} from {$socketId}");

                return;
            }

            $this->fragmentedMessages[$socketId] = $packet;
            $this->fragmentedOpcodes[$socketId] = $opcode;

            return;
        }

        if ($opcode !== 1) {
            $this->debug("Unknown opcode {$opcode} from {$socketId}");

            return;
        }

        // Skip empty packets
        if (empty($packet)) {
            return;
        }

        $data = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            $this->warn("Invalid JSON from socket {$socketId}");
            $this->debug('Payload length: '.strlen($packet));

            return;
        }

        if (isset($data['sn'])) {
            $this->registerSocketSerial($socket, $socketId, (string) $data['sn']);
        }

        $deviceSerial = $this->socketToSerial[$socketId] ?? null;
        $handlerStartedAt = microtime(true);
        $response = $this->processMessage($data, $socket, $deviceSerial);
        $handlerDurationMilliseconds = (microtime(true) - $handlerStartedAt) * 1000;

        if ($handlerDurationMilliseconds >= $this->slowHandlerThresholdMilliseconds()) {
            $commandName = (string) ($data['cmd'] ?? $data['ret'] ?? 'unknown');
            $this->warn(sprintf(
                'Slow message handler: command=%s, serial=%s, duration_ms=%.2f',
                $commandName,
                $deviceSerial ?? 'unknown',
                $handlerDurationMilliseconds
            ));
        }

        if ($response) {
            if (! $this->sendMessage($socket, $response)) {
                $this->handleDisconnection($socket, 'response_write_failed');
            }
        }
    }

    /** Perform WebSocket handshake */
    protected function performHandshake($socket, $buffer): bool
    {
        if (! str_contains($buffer, 'Sec-WebSocket-Key:')) {
            return false;
        }

        $keyStart = strpos($buffer, 'Sec-WebSocket-Key:') + 18;
        $keyEnd = strpos($buffer, "\r\n", $keyStart);
        $key = trim(substr($buffer, $keyStart, $keyEnd - $keyStart));

        $acceptKey = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Sec-WebSocket-Version: 13\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

        return $this->writeToSocket($socket, $response, 'handshake');
    }

    /** Send the message to device
     * @throws JsonException
     */
    public function sendMessage($socket, array $message): bool
    {
        $json = json_encode($message, JSON_THROW_ON_ERROR);
        $length = strlen($json);
        $firstByte = 0x81; // Text frame

        if ($length <= 125) {
            $header = pack('CC', $firstByte, $length);
        } elseif ($length <= 65535) {
            $header = pack('CCn', $firstByte, 126, $length);
        } else {
            $header = pack('CCNN', $firstByte, 127, 0, $length);
        }

        $frame = $header.$json;

        return $this->writeToSocket($socket, $frame, 'message');
    }

    /** Register a socket before closing a superseded connection for the same device. */
    protected function registerSocketSerial($socket, string $socketId, string $serialNumber): void
    {
        $oldSocketIds = [];

        foreach ($this->socketToSerial as $registeredSocketId => $registeredSerial) {
            if ($registeredSerial === $serialNumber && $registeredSocketId !== $socketId) {
                $oldSocketIds[] = $registeredSocketId;
            }
        }

        $this->socketToSerial[$socketId] = $serialNumber;
        $this->connectedDevices[$socketId] = $serialNumber;

        foreach ($oldSocketIds as $oldSocketId) {
            $oldSocket = $this->findSocketById($oldSocketId);

            if ($oldSocket === null || $oldSocket === $socket) {
                continue;
            }

            $this->info("Device {$serialNumber} reconnected with a new socket; closing superseded socket {$oldSocketId}");
            $this->handleDisconnection($oldSocket, 'superseded_connection');
        }
    }

    protected function findSocketById(string $socketId): mixed
    {
        foreach ($this->sockets as $socket) {
            if ($socket !== $this->socket && $this->getSocketId($socket) === $socketId) {
                return $socket;
            }
        }

        return null;
    }

    /** Write the entire payload to a non-blocking socket. */
    protected function writeToSocket($socket, string $payload, string $operation): bool
    {
        $payloadLength = strlen($payload);
        $writtenBytes = 0;
        $deadline = microtime(true) + ($this->writeTimeoutMilliseconds() / 1000);

        while ($writtenBytes < $payloadLength) {
            $written = @socket_write($socket, substr($payload, $writtenBytes));

            if ($written === false) {
                $errorCode = socket_last_error($socket);

                if ($this->isTransientSocketError($errorCode) && microtime(true) < $deadline) {
                    socket_clear_error($socket);

                    if ($this->waitUntilWritable($socket, $deadline)) {
                        continue;
                    }
                }

                $socketId = $this->getSocketId($socket);
                $this->warn("Socket write failed: operation={$operation}, socket={$socketId}, code={$errorCode}, message=".socket_strerror($errorCode));

                return false;
            }

            if ($written === 0) {
                if (microtime(true) >= $deadline || ! $this->waitUntilWritable($socket, $deadline)) {
                    $this->warn("Socket write timed out: operation={$operation}, socket={$this->getSocketId($socket)}");

                    return false;
                }

                continue;
            }

            $writtenBytes += $written;
        }

        return true;
    }

    protected function waitUntilWritable($socket, float $deadline): bool
    {
        $remainingMicroseconds = (int) max(0, min(100000, ($deadline - microtime(true)) * 1000000));

        if ($remainingMicroseconds === 0) {
            return false;
        }

        $read = null;
        $write = [$socket];
        $except = [$socket];
        $selected = @socket_select($read, $write, $except, 0, $remainingMicroseconds);

        return $selected > 0 && $except === [];
    }

    /** Find socket by device serial */
    protected function findSocketBySerial(string $serial): mixed
    {
        $socketId = array_search($serial, $this->socketToSerial, true);

        if ($socketId === false) {
            return null;
        }

        foreach ($this->sockets as $socket) {
            if ($this->getSocketId($socket) === $socketId) {
                return $socket;
            }
        }

        return null;
    }

    protected function isTransientSocketError(int $errorCode): bool
    {
        $transientErrors = [11, 35, 10035];

        foreach (['SOCKET_EAGAIN', 'SOCKET_EWOULDBLOCK', 'SOCKET_EINTR'] as $constantName) {
            if (defined($constantName)) {
                $transientErrors[] = constant($constantName);
            }
        }

        return in_array($errorCode, array_unique($transientErrors), true);
    }

    protected function maxHandshakeBytes(): int
    {
        return max(1024, (int) config('biometric-devices.websocket.max_handshake_bytes', 16384));
    }

    protected function readChunkBytes(): int
    {
        return max(4096, (int) config('biometric-devices.websocket.read_chunk_bytes', 65536));
    }

    protected function maxPayloadBytes(): int
    {
        return max(1024, (int) config('biometric-devices.websocket.max_payload_bytes', 4194304));
    }

    protected function maxBufferBytes(): int
    {
        return max($this->maxPayloadBytes() + 14, (int) config('biometric-devices.websocket.max_buffer_bytes', 8388608));
    }

    protected function writeTimeoutMilliseconds(): int
    {
        return max(100, (int) config('biometric-devices.websocket.write_timeout_ms', 1000));
    }

    protected function slowHandlerThresholdMilliseconds(): int
    {
        return max(1, (int) config('biometric-devices.websocket.slow_handler_threshold_ms', 500));
    }

    protected function pingIntervalSeconds(): int
    {
        return max(1, (int) config('biometric-devices.tasks.ping_interval', 15));
    }

    protected function pingTimeoutSeconds(): int
    {
        return max($this->pingIntervalSeconds() + 1, (int) config('biometric-devices.tasks.ping_timeout', 60));
    }

    protected function registrationTimeoutSeconds(): int
    {
        return max(1, (int) config('biometric-devices.tasks.registration_timeout', 30));
    }

    protected function commandCheckIntervalSeconds(): float
    {
        return max(0.1, (float) config('biometric-devices.tasks.command_check_interval', 1));
    }

    /** Get unique socket ID */
    protected function getSocketId($socket): string
    {
        $objectId = spl_object_id($socket);

        if (isset($this->socketIds[$objectId])) {
            return $this->socketIds[$objectId];
        }

        $address = 'unknown';
        $port = 0;

        try {
            if (! @socket_getpeername($socket, $address, $port)) {
                return $this->socketIds[$objectId] = "socket_{$objectId}";
            }
        } catch (Throwable) {
            return $this->socketIds[$objectId] = "socket_{$objectId}";
        }

        return $this->socketIds[$objectId] = "{$address}_{$port}";
    }

    /** Send pong with the same application data received in ping. */
    protected function sendPong($socket, string $payload = ''): void
    {
        $pong = chr(0x8A).chr(strlen($payload)).$payload;

        if (! $this->writeToSocket($socket, $pong, 'pong')) {
            $this->handleDisconnection($socket, 'pong_write_failed');
        }
    }

    /** Send close frame */
    protected function sendCloseFrame($socket): void
    {
        $close = chr(0x88).chr(0x00);
        $this->writeToSocket($socket, $close, 'close');
    }

    /** Periodic tasks
     * @throws JsonException
     */
    protected function performPeriodicTasks(): void
    {
        $now = microtime(true);

        // Check for dead connections (no pong response after the ping)
        $this->checkDeadConnections($now);

        // Check for unregistered devices (handshake complete but no reg command)
        $this->checkUnregisteredDevices($now);

        if ($now - $this->lastPingTime > $this->pingIntervalSeconds()) {
            $this->sendPingToAll();
            $this->lastPingTime = $now;
        }

        if ($now - $this->lastCommandCheckTime > $this->commandCheckIntervalSeconds()) {
            $this->checkPendingCommands();
            $this->lastCommandCheckTime = $now;
        }
    }

    /** Send a ping to all connected devices */
    protected function sendPingToAll(): void
    {
        $ping = chr(0x89).chr(0x00);
        $now = microtime(true);

        foreach ($this->sockets as $socket) {
            if ($socket === $this->socket) {
                continue;
            }

            $socketId = $this->getSocketId($socket);

            // فقط به دستگاه‌هایی ping می‌فرستیم که:
            // 1. Handshake کامل کرده‌اند
            // 2. Register شده‌اند (serial number دارند)
            if (($this->handshakeComplete[$socketId] ?? false) && isset($this->socketToSerial[$socketId])) {
                if (isset($this->awaitingPong[$socketId])) {
                    continue;
                }

                $result = $this->writeToSocket($socket, $ping, 'ping');

                if ($result) {
                    $this->awaitingPong[$socketId] = $now;
                } else {
                    $this->handleDisconnection($socket, 'ping_write_failed');
                }
            }
        }
    }

    /** Check for connections that haven't responded to ping */
    protected function checkDeadConnections(float $now): void
    {
        $timeout = $this->pingTimeoutSeconds();

        foreach ($this->awaitingPong as $socketId => $pingTime) {
            if ($now - $pingTime > $timeout) {
                // Device hasn't responded to ping: consider it dead
                $this->warn("Connection timeout: {$socketId} (no pong received for {$timeout}s)");

                // Find and disconnect this socket
                foreach ($this->sockets as $socket) {
                    if ($this->getSocketId($socket) === $socketId && $socket !== $this->socket) {
                        $this->handleDisconnection($socket, 'pong_timeout');

                        break;
                    }
                }

                unset($this->awaitingPong[$socketId]);
            }
        }
    }

    /** Check for devices that completed handshake but never registered */
    protected function checkUnregisteredDevices(float $now): void
    {
        $registrationTimeout = $this->registrationTimeoutSeconds();

        foreach ($this->handshakeComplete as $socketId => $isComplete) {
            // اگر handshake کامل شده اما هنوز reg نشده
            if ($isComplete && ! isset($this->socketToSerial[$socketId])) {
                $lastActivity = $this->lastActivityTime[$socketId] ?? 0;

                if ($now - $lastActivity > $registrationTimeout) {
                    $this->warn("Registration timeout: {$socketId} (no reg command for {$registrationTimeout}s)");

                    // Find and disconnect this socket
                    foreach ($this->sockets as $socket) {
                        if ($this->getSocketId($socket) === $socketId && $socket !== $this->socket) {
                            $this->handleDisconnection($socket, 'registration_timeout');

                            break;
                        }
                    }
                }
            }
        }
    }

    /** Check for pending commands and send them
     * @throws JsonException
     */
    protected function checkPendingCommands(): void
    {
        $commandModel = config('biometric-devices.models.device_command');

        // بررسی وجود مدل DeviceCommand
        if (! class_exists($commandModel)) {
            return;
        }

        $this->retryTimedOutCommands($commandModel);

        $inFlightDeviceIds = $commandModel::query()
            ->where('status', DeviceCommandStatusEnum::SENT)
            ->pluck('device_id');

        $candidateLimit = max(10, (int) config('biometric-devices.tasks.command_batch_size', 10) * 10);

        $commands = $commandModel::query()
            ->with('device:id,serial')
            ->where('status', DeviceCommandStatusEnum::PENDING)
            ->where('send_status', false)
            ->when($inFlightDeviceIds->isNotEmpty(), fn ($query) => $query->whereNotIn('device_id', $inFlightDeviceIds))
            ->orderBy('id')
            ->limit($candidateLimit)
            ->get()
            ->unique('device_id')
            ->take(max(1, (int) config('biometric-devices.tasks.command_batch_size', 10)));

        foreach ($commands as $command) {
            $this->sendCommandFromDatabase($command);
        }
    }

    protected function retryTimedOutCommands(string $commandModel): void
    {
        $responseTimeout = max(1, (int) config('biometric-devices.retry.response_timeout', 60));
        $maxAttempts = max(1, (int) config('biometric-devices.retry.max_attempts', 3));

        $timedOutCommands = $commandModel::query()
            ->where('status', DeviceCommandStatusEnum::SENT)
            ->where('updated_at', '<=', now()->subSeconds($responseTimeout))
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($timedOutCommands as $command) {
            $message = "Device response timeout after {$responseTimeout} seconds";

            if ($command->error_count + 1 >= $maxAttempts) {
                $command->markAsFailed($message);
                Logger::warning('Command failed after response timeouts', [
                    'command_id' => $command->id,
                    'device_id' => $command->device_id,
                    'command_name' => $command->command_name,
                    'attempts' => $command->error_count,
                ]);

                continue;
            }

            $command->markForRetry($message);
            Logger::warning('Timed-out command returned to pending queue', [
                'command_id' => $command->id,
                'device_id' => $command->device_id,
                'command_name' => $command->command_name,
                'attempts' => $command->error_count,
            ]);
        }
    }

    /** Send command from database record
     * @throws JsonException
     */
    protected function sendCommandFromDatabase($command): void
    {
        $device = $command->device;

        if (! $device) {
            return;
        }

        // بررسی تعداد retry ها
        if ($command->hasExceededMaxRetries()) {
            $command->markAsFailed('Device not connected after maximum retry attempts');
            Logger::warning("Command failed after max retries: {$device->serial}", [
                'command_id' => $command->id,
                'command_name' => $command->command_name,
                'retry_count' => $command->error_count,
                'max_attempts' => config('biometric-devices.retry.max_attempts', 3),
            ]);

            return;
        }

        // آماده‌سازی params
        $params = json_decode($command->command_content, true, 512, JSON_THROW_ON_ERROR) ?? [];

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

    /** Handle disconnection without marking a superseded device connection offline. */
    protected function handleDisconnection($socket, string $reason = 'unknown', ?int $errorCode = null): void
    {
        $socketId = $this->getSocketId($socket);
        $socketObjectId = spl_object_id($socket);
        $serialNum = $this->socketToSerial[$socketId] ?? null;

        $this->warn("Client disconnected: socket={$socketId}, serial=".($serialNum ?? 'unknown').", reason={$reason}, error_code=".($errorCode ?? 'none'));

        unset($this->socketToSerial[$socketId], $this->connectedDevices[$socketId]);
        $shouldMarkOffline = $serialNum !== null && ! $this->hasActiveConnectionForSerial($serialNum);

        try {
            socket_close($socket);
        } catch (Throwable) {
        }

        $this->sockets = array_filter($this->sockets, fn ($s) => $s !== $socket);
        unset(
            $this->handshakeComplete[$socketId],
            $this->messageBuffer[$socketId],
            $this->lastActivityTime[$socketId],
            $this->awaitingPong[$socketId],
            $this->fragmentedMessages[$socketId],
            $this->fragmentedOpcodes[$socketId]
        );
        unset($this->socketIds[$socketObjectId]);

        if ($shouldMarkOffline) {
            try {
                $this->markDeviceAsOffline($serialNum);
                event(new DeviceDisconnected($serialNum, now()));
                $this->info("Device {$serialNum} disconnected");
            } catch (Throwable $exception) {
                $this->error("Failed to persist disconnection for {$serialNum}: {$exception->getMessage()}");
            }
        } elseif ($serialNum !== null) {
            $this->info("Device {$serialNum} remains online through another active socket");
        }
    }

    protected function hasActiveConnectionForSerial(string $serial): bool
    {
        return in_array($serial, $this->socketToSerial, true);
    }

    /** علامت‌گذاری دستگاه به عنوان آفلاین */
    protected function markDeviceAsOffline(string $serial): void
    {
        $deviceModel = config('biometric-devices.models.device');

        if (! class_exists($deviceModel)) {
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
        $this->running = false;

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
    public function sendAddUser(string $deviceSerial, AddUserDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapAddUserCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setuserinfo', $command);
    }

    /** Send delete user command */
    public function sendDeleteUser(string $deviceSerial, DeleteUserDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapDeleteUserCommand($dto);

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
    public function sendGetUserInfo(string $deviceSerial, GetUserInfoDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapGetUserInfoCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'getuserinfo', $command);
    }

    // ============================================
    // Device Control
    // ============================================

    /** Send open door command */
    public function sendOpenDoor(string $deviceSerial, OpenDoorDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapOpenDoorCommand($dto);

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
    public function sendSetTime(string $deviceSerial, SetTimeDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapSetTimeCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'settime', $command);
    }

    // ============================================
    // Access Control
    // ============================================

    /** Send set user access command */
    public function sendSetUserAccess(string $deviceSerial, SetUserAccessDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapSetUserAccessCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setuserlock', $command);
    }

    /** Send set device lock command */
    public function sendSetDeviceLock(string $deviceSerial, SetDeviceLockDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapSetDeviceLockCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'setdevlock', $command);
    }

    // ============================================
    // Attendance Logs
    // ============================================

    /** Send get all logs command */
    public function sendGetAllLogs(string $deviceSerial, GetLogsDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapGetLogsCommand($dto, 'getalllog');

        return $this->sendRawCommand($deviceSerial, 'getalllog', $command);
    }

    /** Send the get new logs command */
    public function sendGetNewLogs(string $deviceSerial, GetLogsDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapGetLogsCommand($dto, 'getnewlog');

        return $this->sendRawCommand($deviceSerial, 'getnewlog', $command);
    }

    public function sendOpenLock(string $deviceSerial, OpenLockDTO $dto): bool
    {
        $command = app(DataMapperInterface::class)->mapOpenLockCommand($dto);

        return $this->sendRawCommand($deviceSerial, 'openLock', $command);
    }

    /** Send raw command to device */
    public function sendRawCommand(string $deviceSerial, string $commandName, array $params): bool
    {
        $socketId = array_search($deviceSerial, $this->connectedDevices, true);

        if ($socketId === false) {
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

        if (! $socket) {
            Logger::debug("Socket not found for device: {$deviceSerial}");

            return false;
        }

        try {
            $sent = $this->sendMessage($socket, $params);
        } catch (Throwable $e) {
            Logger::error("Failed to send command to device: {$deviceSerial}", [
                'command' => $commandName,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $sent) {
            Logger::warning("Command write failed for device: {$deviceSerial}", [
                'command' => $commandName,
                'params' => $params,
            ]);

            return false;
        }

        Logger::debug("Command sent to device: {$deviceSerial}", [
            'command' => $commandName,
            'params' => $params,
        ]);

        return true;
    }
}
