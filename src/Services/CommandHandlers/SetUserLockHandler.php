<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class SetUserLockHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        $this->log('SetUserLockHandler:Set user lock response', [
            'pure'    => $data,
            'success' => $result,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'setuserlock', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'setuserlock',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'setuserlock';
    }
}
