<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use JsonException;
use Sajadsoft\BiometricDevices\Events\FingerprintEnrollmentStarted;

/**
 * Handler for adduser command response from device
 *
 * این Handler پاسخ دستور adduser را پردازش می‌کند
 * دستگاه بعد از دریافت این دستور، وارد حالت ثبت اثر انگشت می‌شود
 * و منتظر می‌ماند تا کاربر انگشت خود را روی سنسور قرار دهد
 *
 * بعد از این پاسخ، باید به صورت مداوم checkregstatus را فراخوانی کنیم
 * تا وضعیت فرآیند ثبت را دریافت کنیم
 */
class AddUserHandler extends BaseCommandHandler
{
    /**
     * Handle adduser response from device
     *
     * @param array{
     *     ret: string,
     *     sn: string,
     *     enrollid: int,
     *     backupnum: int,
     *     result: bool,
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

        $enrollId = $data['enrollid'] ?? 0;
        $backupNum = $data['backupnum'] ?? 0;
        $result = $data['result'] ?? false;

        $this->log('AddUserHandler: Fingerprint enrollment initiated', [
            'device_serial' => $serialNum,
            'employee_id' => $enrollId,
            'backup_num' => $backupNum,
            'result' => $result,
            'raw_data' => $data,
        ]);

        // بروزرسانی وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'adduser', $result, $data);

        // پخش Event - برای شروع polling checkregstatus
        if ($result) {
            event(new FingerprintEnrollmentStarted(
                deviceSerial: $serialNum,
                employeeId: $enrollId,
                backupNum: $backupNum,
                result: $result
            ));
        }

        // پاسخ موفق به دستگاه
        return $this->buildResponse('adduser', true);
    }

    /** Get command name handled by this handler */
    public function getCommandName(): string
    {
        return 'adduser';
    }
}
