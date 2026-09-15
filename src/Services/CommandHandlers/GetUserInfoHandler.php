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
        if (! isset($data['enrollid'])) {
            return null;
        }

        $serialNum = $this->getDeviceSerial($data);

        // تبدیل به DTO
        $userDTO = $this->mapper->mapToUserDTO($data);

        $this->log('GetUserInfoHandler:User info received', [
            'pure' => $data,
            'mapped' => $userDTO->toArray(),
        ]);

        // result:false یعنی دستگاه کاربر را پیدا نکرد (داده بیومتریک وجود ندارد)
        $deviceFoundUser = ($data['result'] ?? false) === true;

        // بررسی وجود command مرتبط در دیتابیس و بروزرسانی وضعیت آن
        $hasCommand = $this->updateCommandStatus($serialNum, 'getuserinfo', $deviceFoundUser, $data);

        // فقط در صورت وجود command و وقتی دستگاه واقعاً داده برگردانده، event فایر کن
        // اگر result:false باشد (کاربر در دستگاه نیست)، event نباید فایر شود
        if ($hasCommand && $deviceFoundUser) {
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

        // The protocol defines getuserinfo as a server request followed by one device response.
        // Sending an extra ACK here can put some firmware versions into an invalid state.
        return null;
    }

    public function getCommandName(): string
    {
        return 'getuserinfo';
    }
}
