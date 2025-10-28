<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for device info response
 */
class GetDeviceInfoHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if (! $serialNum) {
            return null;
        }

        // تبدیل به DTO
        $deviceInfoDTO = $this->mapper->mapToDeviceInfoDTO($data);

        $this->log('Device info received', [
            'model' => $deviceInfoDTO->modelName,
            'firmware' => $deviceInfoDTO->firmwareVersion,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'getdevinfo', true, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'getdevinfo',
            success: true,
            responseData: $data
        ));

        return null;
    }

    public function getCommandName(): string
    {
        return 'getdevinfo';
    }
}
