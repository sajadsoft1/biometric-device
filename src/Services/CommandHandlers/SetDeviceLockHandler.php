<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class SetDeviceLockHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        $this->log('SetDeviceLockHandler:Set device lock response', [
            'pure'    => $data,
            'success' => $result,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'setdevlock', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'setdevlock',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'setdevlock';
    }
}
