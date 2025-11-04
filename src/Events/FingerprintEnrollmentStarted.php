<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

/**
 * Event fired when fingerprint enrollment is initiated on device
 *
 * این Event زمانی fire می‌شود که دستگاه تأیید کند که وارد حالت
 * ثبت اثر انگشت شده است و منتظر اسکن اثر انگشت کاربر می‌ماند
 *
 * استفاده برای:
 * - نمایش پیام "لطفاً انگشت خود را روی سنسور قرار دهید" به کاربر
 * - شروع polling برای checkregstatus
 * - ثبت لاگ شروع فرآیند ثبت‌نام
 */
class FingerprintEnrollmentStarted
{
    /**
     * Create a new event instance
     *
     * @param string $deviceSerial شماره سریال دستگاه
     * @param int    $employeeId   شناسه کاربر
     * @param int    $backupNum    شماره slot اثر انگشت
     * @param bool   $result       آیا دستور با موفقیت ارسال شد؟
     */
    public function __construct(
        public string $deviceSerial,
        public int $employeeId,
        public int $backupNum,
        public bool $result,
    ) {}
}
