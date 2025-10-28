<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;
use Sajadsoft\BiometricDevices\Events\UserInfoReceived;

/**
 * Handler for user info response from device
 *
 * ⚠️ IMPORTANT: دستگاه ممکن است به صورت خودکار اطلاعات کاربر را ارسال کند
 * Event ها فقط زمانی trigger می‌شوند که command واقعی در دیتابیس وجود داشته باشد
 */
class GetUserInfoHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        if ( ! isset($data['enrollid'])) {
            return null;
        }

        $serialNum = $this->getDeviceSerial($data);

        // تبدیل به DTO
        $userDTO = $this->mapper->mapToUserDTO($data);

        $this->log('GetUserInfoHandler:User info received', [
            'pure'   => $data,
            'mapped' => $userDTO->toArray(),
        ]);

        // بررسی وجود command مرتبط در دیتابیس
        $hasCommand = $this->updateCommandStatus($serialNum, 'getuserinfo', true, $data);

        // فقط در صورت وجود command، event ها را trigger کن
        // دستگاه به صورت خودکار و دوره‌ای اطلاعات کاربران را می‌فرستد
        // اما ما فقط وقتی نیاز داریم که واقعاً درخواست داده‌ایم
        if ($hasCommand) {
            // پخش Event - کاربر مسئول ذخیره
            event(new UserInfoReceived($userDTO));

            // پخش Event برای اطلاع‌رسانی
            event(new CommandResponseReceived(
                deviceSerial: $serialNum,
                commandName: 'getuserinfo',
                success: true,
                responseData: $data
            ));
        }

        return null;
    }

    public function getCommandName(): string
    {
        return 'getuserinfo';
    }
}
