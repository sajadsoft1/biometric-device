<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for set time response from device
 *
 * دستور settime زمان سیستم دستگاه را تنظیم می‌کند
 */
class SetTimeHandler extends BaseCommandHandler
{
    /**
     * Handle set time response from device
     *
     * @param array{
     *     ret: string,
     *     result: bool,
     *     sn?: string,
     * } $data پاسخ دستگاه
     * @param mixed $connection اتصال فعال
     *
     * @return array{
     *     ret: string,
     *     result: bool,
     * }|null پاسخ برای دستگاه (null = بدون پاسخ)
     */
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        $this->log('SetTimeHandler:Set time response received', [
            'pure'    => $data,
            'success' => $result,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'settime', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'settime',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    /** Get command name handled by this handler */
    public function getCommandName(): string
    {
        return 'settime';
    }
}
