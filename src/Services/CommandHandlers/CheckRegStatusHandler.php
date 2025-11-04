<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use JsonException;
use Sajadsoft\BiometricDevices\DTOs\Responses\RegistrationStatusDTO;
use Sajadsoft\BiometricDevices\Events\RegistrationStatusReceived;

/**
 * Handler for registration status messages from device
 *
 * دستگاه در حین فرآیند ثبت‌نام بیومتریک (اثر انگشت، کارت، تصویر)
 * پیام‌های راهنمای وضعیت را به صورت real-time ارسال می‌کند
 *
 * این Handler این پیام‌ها را دریافت و به Event تبدیل می‌کند تا
 * در frontend به کاربر نمایش داده شود
 */
class CheckRegStatusHandler extends BaseCommandHandler
{
    /**
     * Handle registration status message from device
     *
     * @param array{
     *     ret: string,
     *     sn: string,
     *     result: bool,
     *     status: int,
     *     msg?: string,
     *     enrollid?: int,
     *     backupnum?: int,
     *     cancel?: bool,
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

        // تبدیل به DTO
        $statusDTO = new RegistrationStatusDTO(
            deviceSerial: $serialNum,
            result: $data['result'] ?? false,
            status: $data['status'] ?? 0,
            message: $data['msg'] ?? null,        // اختیاری - گاهی نمی‌آید
            timestamp: now(),
            employeeId: $data['enrollid'] ?? null,
            backupNum: $data['backupnum'] ?? null,
            isCancelled: $data['cancel'] ?? false, // آیا کاربر کنسل کرده؟
            image: $data['image'] ?? null,         // تصویر اثر انگشت Base64
            rawData: $data
        );

        $this->log('CheckRegStatusHandler:Registration status received', [
            'pure'         => $data,
            'mapped'       => $statusDTO->toArray(),
            'message'      => $statusDTO->getUserMessage(),
            'status'       => $statusDTO->getStatusLabel(),
            'is_cancelled' => $statusDTO->isCancelled,
        ]);

        // پخش Event - برای نمایش real-time به کاربر
        event(new RegistrationStatusReceived($statusDTO));

        // پاسخ موفق به دستگاه
        return $this->buildResponse('checkregstatus', true);
    }

    /** Get command name handled by this handler */
    public function getCommandName(): string
    {
        return 'checkregstatus';
    }
}
