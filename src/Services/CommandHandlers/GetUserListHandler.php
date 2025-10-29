<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;
use Sajadsoft\BiometricDevices\Events\UserListReceived;

/**
 * Handler for user list response from device
 */
class GetUserListHandler extends BaseCommandHandler
{
    /**
     * Undocumented function
     *
     * @param array{
     *  record: array<array{
     *      enrollid: int,
     *      backupnum: int,
     *      name: string,
     *      admin: int,
     *      sn: string,
     *  }>,
     *  count: int,
     *  stn: bool,
     * } $data
     * @param mixed $connection
     */
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        // تبدیل records به EnrollmentDTO
        $enrollments = [];
        if (isset($data['record']) && is_array($data['record'])) {
            foreach ($data['record'] as $record) {
                $record['sn']  = $serialNum;
                $enrollments[] = $this->mapper->mapToEnrollmentDTO($record);
            }
        }

        $count          = count($enrollments);
        $remainingCount = $data['count'] ?? 0;
        $hasMore        = $remainingCount > 0;

        $this->log('GetUserListHandler:User list received', [
            'pure'      => $data,
            'count'     => $count,
            'has_more'  => $hasMore,
            'remaining' => $remainingCount,
        ]);

        // بروزرسانی خودکار وضعیت دستور در دیتابیس
        $this->updateCommandStatus($serialNum, 'getuserlist', true, $data);

        // پخش Event - کاربر مسئول ذخیره
        event(new UserListReceived($serialNum, $enrollments, $count, $hasMore));

        // پخش Event برای اطلاع‌رسانی
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: 'getuserlist',
            success: true,
            responseData: $data
        ));

        // اگر count > 0، درخواست ادامه
        if ($hasMore) {
            return [
                'cmd' => 'getuserlist',
                'stn' => false,
            ];
        }

        // وقتی دیگر رکوردی نیست، هیچ پاسخی لازم نیست
        // دستگاه متوجه می‌شود که count=0 یعنی تمام
        return null;
    }

    public function getCommandName(): string
    {
        return 'getuserlist';
    }
}
