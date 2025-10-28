<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class DeleteUserHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if (! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'deleteuser', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'deleteuser',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'deleteuser';
    }
}
