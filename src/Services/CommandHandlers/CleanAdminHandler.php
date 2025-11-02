<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use JsonException;
use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for clean admin response from device
 *
 * دستور cleanadmin تمام کاربران با سطح دسترسی ادمین را حذف می‌کند
 * ⚠️ این دستور خطرناک است و باید با احتیاط استفاده شود
 */
class CleanAdminHandler extends BaseCommandHandler
{
    /**
     * Handle clean admin response from device
     *
     * @param array{
     *     ret: string,
     *     result: bool,
     *     sn?: string,
     * }            $data       پاسخ دستگاه
     * @param mixed $connection اتصال فعال
     *
     * @return array{
     *     ret: string,
     *     result: bool,
     * }|null پاسخ برای دستگاه (null = بدون پاسخ)
     * @throws JsonException
     */
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $result = $data['result'] ?? false;

        $this->log('CleanAdminHandler:Clean admin response received', [
            'pure'    => $data,
            'success' => $result,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'cleanadmin', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'cleanadmin',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    /** Get command name handled by this handler */
    public function getCommandName(): string
    {
        return 'cleanadmin';
    }
}
