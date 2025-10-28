<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;
use Sajadsoft\BiometricDevices\Events\UserInfoReceived;

/**
 * Handler for senduser command from device
 *
 * ⚠️ IMPORTANT: دستگاه به صورت دوره‌ای و خودکار این command را ارسال می‌کند
 * Event ها فقط زمانی trigger می‌شوند که command واقعی در دیتابیس وجود داشته باشد
 * این از لاگ‌های بی‌مورد و پردازش‌های اضافی جلوگیری می‌کند
 */
class SendUserHandler extends BaseCommandHandler
{
    /**
     * Undocumented function
     *
     * @param array{
     *  enrollid: int,
     *  name: string,
     *  backupnum: int,
     *  admin: int,
     *  record: int,
     *  card: int, //card number
     *  pwd: int, //password
     *  enable: int,
     *  shiftid: int,
     *  sn: string,
     * } $data
     * @param mixed $connection
     */
    public function handle(array $data, $connection): ?array
    {
        if ( ! isset($data['enrollid'])) {
            return null;
        }

        $serialNum = $this->getDeviceSerial($data);

        // تبدیل به DTO
        $userDTO = $this->mapper->mapToUserDTO($data);

        // بررسی وجود command مرتبط در دیتابیس
        $hasCommand = $this->updateCommandStatus($serialNum, 'senduser', true, $data);

        // فقط در صورت وجود command، event ها را trigger کن
        // دستگاه به صورت خودکار و دوره‌ای اطلاعات کاربران را می‌فرستد
        // اما ما فقط وقتی نیاز داریم که واقعاً درخواست داده‌ایم
        if ($hasCommand) {
            $this->log('SendUserHandler:User info sent by device', [
                'pure'   => $data,
                'mapped' => $userDTO->toArray(),
            ]);
            // پخش Event - کاربر مسئول ذخیره
            event(new UserInfoReceived($userDTO));

            // پخش Event برای اطلاع‌رسانی
            event(new CommandResponseReceived(
                deviceSerial: $serialNum,
                commandName: 'senduser',
                success: true,
                responseData: $data
            ));
        }

        return null;
    }

    public function getCommandName(): string
    {
        return 'senduser';
    }
}
