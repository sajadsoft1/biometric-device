<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Sajadsoft\BiometricDevices\DTOs\Responses\RegistrationStatusDTO;

/**
 * Event fired when registration status is received from device
 *
 * این Event زمانی fire می‌شود که دستگاه در حین فرآیند ثبت‌نام بیومتریک
 * پیام راهنمای وضعیت را ارسال می‌کند (مثل "ورود کارت"، "لمس اثر انگشت")
 *
 * استفاده برای:
 * - نمایش پیام‌های راهنما به کاربر در UI
 * - ردیابی فرآیند ثبت‌نام
 * - لاگ گرفتن از مراحل ثبت‌نام
 */
class RegistrationStatusReceived
{
    /**
     * Create a new event instance
     *
     * @param RegistrationStatusDTO $status اطلاعات وضعیت ثبت‌نام شامل پیام راهنما و status code
     */
    public function __construct(
        public RegistrationStatusDTO $status,
    ) {}
}
