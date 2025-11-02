<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use JsonException;
use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for clean log response from device
 *
 * دستور cleanlog تمام لاگ‌های حضور و غیاب را از دستگاه پاک می‌کند
 */
class CleanLogHandler extends BaseCommandHandler
{
    /**
     * Handle clean log response from device
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

        $this->log('CleanLogHandler:Clean log response received', [
            'pure'    => $data,
            'success' => $result,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'cleanlog', $result, $data);

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'cleanlog',
            success: $result,
            responseData: $data
        ));

        return null;
    }

    /** Get command name handled by this handler */
    public function getCommandName(): string
    {
        return 'cleanlog';
    }
}
