<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests\Unit;

use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Services\CommandHandlers\BaseCommandHandler;
use Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper;
use Sajadsoft\BiometricDevices\Tests\TestCase;

class CommandStatusMatchingTest extends TestCase
{
    public function test_response_completes_oldest_sent_command_instead_of_newer_pending_command(): void
    {
        $device = Device::query()->create([
            'serial' => 'SERIAL-1',
            'name' => 'Device',
        ]);
        $sentCommand = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'command_name' => 'getuserinfo',
            'command_content' => '{}',
            'status' => DeviceCommandStatusEnum::SENT,
            'send_status' => true,
        ]);
        $pendingCommand = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'command_name' => 'getuserinfo',
            'command_content' => '{}',
        ]);

        $handler = new TestCommandHandler(new AIFaceWebSocketMapper);
        $updated = $handler->complete('SERIAL-1', 'getuserinfo');

        self::assertTrue($updated);
        self::assertSame(DeviceCommandStatusEnum::SUCCESS, $sentCommand->fresh()->status);
        self::assertSame(DeviceCommandStatusEnum::PENDING, $pendingCommand->fresh()->status);
    }
}

class TestCommandHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        return null;
    }

    public function getCommandName(): string
    {
        return 'test';
    }

    public function complete(string $serial, string $commandName): bool
    {
        return $this->updateCommandStatus($serial, $commandName, true);
    }
}
