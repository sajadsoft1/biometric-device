<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for door open response
 */
class OpenDoorHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if (! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        $this->log($result ? 'Door opened successfully' : 'Door open failed');

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'opendoor', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'opendoor',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'opendoor';
    }
}
