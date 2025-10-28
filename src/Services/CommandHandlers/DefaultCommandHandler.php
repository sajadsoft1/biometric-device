<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Default handler for unimplemented commands
 */
class DefaultCommandHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $commandName = $data['ret'] ?? 'unknown';
        $result      = $data['result'] ?? true;

        $this->log("Default handler for: {$commandName}");

        // پخش Event
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: $commandName,
            success: $result,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'default';
    }
}
