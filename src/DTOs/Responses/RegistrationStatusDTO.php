<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Carbon\Carbon;

/**
 * Registration status data from device during biometric enrollment
 *
 * این DTO وضعیت فرآیند ثبت‌نام بیومتریک را نگهداری می‌کند
 * دستگاه در حین ثبت اثر انگشت، کارت یا تصویر، پیام‌های راهنما ارسال می‌کند
 */
readonly class RegistrationStatusDTO
{
    public function __construct(
        public string $deviceSerial,     // شماره سریال دستگاه
        public bool $result,             // نتیجه موفق/ناموفق
        public int $status,              // کد وضعیت (0 = شروع فرآیند، 1 = موفقیت مرحله، 2 = ناموفق)
        public ?string $message,         // پیام راهنمای فارسی (اختیاری - گاهی دستگاه نمی‌فرستد)
        public Carbon $timestamp,        // زمان دریافت
        public ?int $employeeId,         // شناسه کاربر (در صورت وجود)
        public ?int $backupNum,          // نوع بیومتریک در حال ثبت (در صورت وجود)
        public bool $isCancelled,        // آیا کاربر فرآیند را کنسل کرده؟
        public array $rawData,           // داده‌های خام دستگاه
    ) {}

    /** Convert to array */
    public function toArray(): array
    {
        return [
            'device_serial' => $this->deviceSerial,
            'result'        => $this->result,
            'status'        => $this->status,
            'status_label'  => $this->getStatusLabel(),
            'message'       => $this->message,
            'is_cancelled'  => $this->isCancelled,
            'timestamp'     => $this->timestamp->toIso8601String(),
            'employee_id'   => $this->employeeId,
            'backup_num'    => $this->backupNum,
            'raw_data'      => $this->rawData,
        ];
    }

    /** Get human-readable status label */
    public function getStatusLabel(): string
    {
        if ($this->isCancelled) {
            return 'لغو شده';
        }

        return match ($this->status) {
            0       => 'شروع فرآیند',
            1       => 'موفقیت مرحله',
            2       => 'ناموفق',
            default => 'نامشخص',
        };
    }

    /** Check if registration is in progress (started) */
    public function isInProgress(): bool
    {
        return $this->status === 0 && ! $this->isCancelled;
    }

    /** Check if registration step is successful */
    public function isStepSuccessful(): bool
    {
        return $this->status === 1 && ! $this->isCancelled;
    }

    /** Check if registration has failed */
    public function hasFailed(): bool
    {
        return $this->status === 2;
    }

    /** Check if registration was cancelled by user */
    public function wasCancelled(): bool
    {
        return $this->isCancelled;
    }

    /**
     * Get user-friendly message
     * اگر پیام از دستگاه نیامده، یک پیام پیش‌فرض برمی‌گرداند
     */
    public function getUserMessage(): string
    {
        if ($this->message) {
            return $this->message;
        }

        // پیام‌های پیش‌فرض بر اساس status
        if ($this->isCancelled) {
            return 'فرآیند ثبت‌نام لغو شد';
        }

        return match ($this->status) {
            0       => 'در حال شروع فرآیند ثبت...',
            1       => 'مرحله با موفقیت انجام شد',
            2       => 'خطا در فرآیند ثبت',
            default => 'در حال پردازش...',
        };
    }
}
