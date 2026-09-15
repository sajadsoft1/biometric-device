<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests\Unit;

use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Services\DeviceDrivers\WebSocketDeviceDriver;
use Sajadsoft\BiometricDevices\Tests\TestCase;
use Socket;

class WebSocketDeviceDriverTest extends TestCase
{
    /** @var array<int, Socket> */
    private array $openSockets = [];

    protected function tearDown(): void
    {
        foreach ($this->openSockets as $socket) {
            try {
                socket_close($socket);
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    public function test_transient_non_blocking_read_does_not_disconnect_device(): void
    {
        [$serverSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket, 'SERIAL-1');

        $driver->readSocket($serverSocket);

        self::assertSame(['SERIAL-1'], $driver->getConnectedDevices());
        self::assertSame(0, $driver->offlineCount);
    }

    public function test_superseded_connection_does_not_mark_device_offline(): void
    {
        [$oldServerSocket] = $this->createTcpSocketPair();
        [$newServerSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($oldServerSocket, 'SERIAL-1');
        $driver->addConnectedSocket($newServerSocket);

        $driver->registerSerial($newServerSocket, 'SERIAL-1');

        self::assertSame(['SERIAL-1'], $driver->getConnectedDevices());
        self::assertSame(0, $driver->offlineCount);
    }

    public function test_real_disconnect_marks_device_offline_when_no_replacement_exists(): void
    {
        [$serverSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket, 'SERIAL-1');

        $driver->disconnectSocket($serverSocket);

        self::assertSame([], $driver->getConnectedDevices());
        self::assertSame(1, $driver->offlineCount);
    }

    public function test_exact_empty_pong_frame_is_processed_without_waiting_for_more_data(): void
    {
        [$serverSocket, $clientSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket, 'SERIAL-1');
        $driver->markAwaitingPong($serverSocket);

        socket_write($clientSocket, "\x8A\x00");
        $driver->readSocket($serverSocket);

        self::assertFalse($driver->isAwaitingPong($serverSocket));
    }

    public function test_large_fragmented_message_is_reassembled_across_multiple_socket_reads(): void
    {
        [$serverSocket, $clientSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket);

        $record = str_repeat('A', 120000);
        $json = json_encode([
            'ret' => 'getuserinfo',
            'sn' => 'SERIAL-1',
            'enrollid' => 10,
            'record' => $record,
        ], JSON_THROW_ON_ERROR);
        $splitPosition = 60000;
        $frames = $this->makeClientFrame(substr($json, 0, $splitPosition), 1, false)
            .$this->makeClientFrame(substr($json, $splitPosition), 0, true);

        $this->writeAll($clientSocket, $frames);

        for ($attempt = 0; $attempt < 100 && $driver->receivedMessages === []; $attempt++) {
            $driver->readSocket($serverSocket);
        }

        self::assertCount(1, $driver->receivedMessages);
        self::assertSame(120000, strlen($driver->receivedMessages[0]['record']));
        self::assertSame(['SERIAL-1'], $driver->getConnectedDevices());
    }

    public function test_malformed_frame_does_not_stop_processing_following_frames(): void
    {
        [$serverSocket, $clientSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket);

        $validMessage = json_encode([
            'ret' => 'getdevinfo',
            'sn' => 'SERIAL-1',
        ], JSON_THROW_ON_ERROR);
        $frames = $this->makeClientFrame('{', 1, true)
            . $this->makeClientFrame($validMessage, 1, true);

        $this->writeAll($clientSocket, $frames);
        $driver->readSocket($serverSocket);

        self::assertCount(1, $driver->receivedMessages);
        self::assertSame('getdevinfo', $driver->receivedMessages[0]['ret']);
    }

    public function test_ping_timestamp_is_not_overwritten_before_a_response(): void
    {
        [$serverSocket] = $this->createTcpSocketPair();
        $driver = new TestWebSocketDeviceDriver;
        $driver->addConnectedSocket($serverSocket, 'SERIAL-1');

        $driver->sendPings();
        $firstPingTime = $driver->pingTime($serverSocket);
        usleep(1000);
        $driver->sendPings();

        self::assertSame($firstPingTime, $driver->pingTime($serverSocket));
    }

    public function test_pending_commands_are_serialized_per_device(): void
    {
        $firstDevice = Device::query()->create([
            'serial' => 'SERIAL-1',
            'name' => 'First device',
        ]);
        $secondDevice = Device::query()->create([
            'serial' => 'SERIAL-2',
            'name' => 'Second device',
        ]);

        $firstCommand = DeviceCommand::query()->create([
            'device_id' => $firstDevice->id,
            'command_name' => 'getuserinfo',
            'command_content' => json_encode(['cmd' => 'getuserinfo'], JSON_THROW_ON_ERROR),
        ]);
        DeviceCommand::query()->create([
            'device_id' => $firstDevice->id,
            'command_name' => 'getuserinfo',
            'command_content' => json_encode(['cmd' => 'getuserinfo'], JSON_THROW_ON_ERROR),
        ]);
        DeviceCommand::query()->create([
            'device_id' => $secondDevice->id,
            'command_name' => 'checkregstatus',
            'command_content' => json_encode(['cmd' => 'checkregstatus'], JSON_THROW_ON_ERROR),
            'status' => DeviceCommandStatusEnum::SENT,
            'send_status' => true,
        ]);
        DeviceCommand::query()->create([
            'device_id' => $secondDevice->id,
            'command_name' => 'getuserinfo',
            'command_content' => json_encode(['cmd' => 'getuserinfo'], JSON_THROW_ON_ERROR),
        ]);

        $driver = new TestWebSocketDeviceDriver;
        $driver->sendPendingCommands();

        self::assertSame([['SERIAL-1', 'getuserinfo']], $driver->sentCommands);
        self::assertSame(DeviceCommandStatusEnum::SENT, $firstCommand->fresh()->status);
        self::assertSame(
            1,
            DeviceCommand::query()
                ->where('device_id', $firstDevice->id)
                ->where('status', DeviceCommandStatusEnum::SENT)
                ->count()
        );
    }

    public function test_timed_out_command_is_retried_and_cannot_block_device_forever(): void
    {
        $device = Device::query()->create([
            'serial' => 'SERIAL-1',
            'name' => 'Device',
        ]);
        $command = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'command_name' => 'getuserinfo',
            'command_content' => json_encode(['cmd' => 'getuserinfo'], JSON_THROW_ON_ERROR),
            'status' => DeviceCommandStatusEnum::SENT,
            'send_status' => true,
        ]);
        $command->forceFill(['updated_at' => now()->subSeconds(61)])->saveQuietly();

        $driver = new TestWebSocketDeviceDriver;
        $driver->sendPendingCommands();

        self::assertSame([['SERIAL-1', 'getuserinfo']], $driver->sentCommands);
        self::assertSame(DeviceCommandStatusEnum::SENT, $command->fresh()->status);
        self::assertSame(1, $command->fresh()->error_count);
    }

    public function test_timed_out_command_fails_after_maximum_attempts(): void
    {
        $device = Device::query()->create([
            'serial' => 'SERIAL-1',
            'name' => 'Device',
        ]);
        $command = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'command_name' => 'getuserinfo',
            'command_content' => json_encode(['cmd' => 'getuserinfo'], JSON_THROW_ON_ERROR),
            'status' => DeviceCommandStatusEnum::SENT,
            'send_status' => true,
            'error_count' => 2,
        ]);
        $command->forceFill(['updated_at' => now()->subSeconds(61)])->saveQuietly();

        $driver = new TestWebSocketDeviceDriver;
        $driver->sendPendingCommands();

        self::assertSame([], $driver->sentCommands);
        self::assertSame(DeviceCommandStatusEnum::FAILED, $command->fresh()->status);
        self::assertSame(3, $command->fresh()->error_count);
    }

    /** @return array{Socket, Socket} */
    private function createTcpSocketPair(): array
    {
        $listener = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($listener, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($listener, '127.0.0.1', 0);
        socket_getsockname($listener, $address, $port);
        socket_listen($listener, 1);

        $client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($client, $address, $port);
        $server = socket_accept($listener);
        socket_close($listener);
        socket_set_nonblock($server);

        $this->openSockets[] = $server;
        $this->openSockets[] = $client;

        return [$server, $client];
    }

    private function makeClientFrame(string $payload, int $opcode, bool $fin): string
    {
        $firstByte = ($fin ? 0x80 : 0x00) | $opcode;
        $payloadLength = strlen($payload);
        $mask = "\x12\x34\x56\x78";

        if ($payloadLength <= 125) {
            $header = pack('CC', $firstByte, 0x80 | $payloadLength);
        } elseif ($payloadLength <= 65535) {
            $header = pack('CCn', $firstByte, 0x80 | 126, $payloadLength);
        } else {
            $header = pack('CCNN', $firstByte, 0x80 | 127, 0, $payloadLength);
        }

        $maskedPayload = '';
        for ($index = 0; $index < $payloadLength; $index++) {
            $maskedPayload .= $payload[$index] ^ $mask[$index % 4];
        }

        return $header.$mask.$maskedPayload;
    }

    private function writeAll(Socket $socket, string $payload): void
    {
        $offset = 0;

        while ($offset < strlen($payload)) {
            $written = socket_write($socket, substr($payload, $offset));
            self::assertNotFalse($written);
            $offset += $written;
        }
    }
}

class TestWebSocketDeviceDriver extends WebSocketDeviceDriver
{
    /** @var array<int, array<string, mixed>> */
    public array $receivedMessages = [];

    public int $offlineCount = 0;

    /** @var array<int, array{string, string}> */
    public array $sentCommands = [];

    public function __construct()
    {
        parent::__construct();
        $this->socket = null;
    }

    public function addConnectedSocket(Socket $socket, ?string $serial = null): void
    {
        $socketId = $this->getSocketId($socket);
        $this->sockets[] = $socket;
        $this->handshakeComplete[$socketId] = true;
        $this->lastActivityTime[$socketId] = microtime(true);

        if ($serial !== null) {
            $this->socketToSerial[$socketId] = $serial;
            $this->connectedDevices[$socketId] = $serial;
        }
    }

    public function readSocket(Socket $socket): void
    {
        $this->handleSocketActivity($socket);
    }

    public function registerSerial(Socket $socket, string $serial): void
    {
        $this->registerSocketSerial($socket, $this->getSocketId($socket), $serial);
    }

    public function disconnectSocket(Socket $socket): void
    {
        $this->handleDisconnection($socket, 'test_disconnect');
    }

    public function markAwaitingPong(Socket $socket): void
    {
        $this->awaitingPong[$this->getSocketId($socket)] = microtime(true);
    }

    public function isAwaitingPong(Socket $socket): bool
    {
        return isset($this->awaitingPong[$this->getSocketId($socket)]);
    }

    public function sendPings(): void
    {
        $this->sendPingToAll();
    }

    public function pingTime(Socket $socket): ?float
    {
        return $this->awaitingPong[$this->getSocketId($socket)] ?? null;
    }

    public function sendPendingCommands(): void
    {
        $this->checkPendingCommands();
    }

    public function sendRawCommand(string $deviceSerial, string $commandName, array $params): bool
    {
        $this->sentCommands[] = [$deviceSerial, $commandName];

        return true;
    }

    protected function processMessage(array $data, $connection, ?string $deviceSerial = null): ?array
    {
        $this->receivedMessages[] = $data;

        return null;
    }

    protected function markDeviceAsOffline(string $serial): void
    {
        $this->offlineCount++;
    }
}
