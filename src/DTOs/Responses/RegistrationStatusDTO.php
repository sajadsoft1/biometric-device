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
        public int $status,              // کد وضعیت (1 = اولین اسکن، 2 = دومین اسکن، 3 = سومین اسکن، 100 = موفقیت کامل)
        public ?string $message,         // پیام راهنمای فارسی (اختیاری - گاهی دستگاه نمی‌فرستد)
        public Carbon $timestamp,        // زمان دریافت
        public ?int $employeeId,         // شناسه کاربر (در صورت وجود)
        public ?int $backupNum,          // نوع بیومتریک در حال ثبت (در صورت وجود)
        public bool $isCancelled,        // آیا کاربر فرآیند را کنسل کرده؟
        public ?string $image,           // تصویر اثر انگشت Base64 (اختیاری)
        public array $rawData,           // داده‌های خام دستگاه
    ) {}

    /** Convert to array */
    public function toArray(): array
    {
        return [
            'device_serial' => $this->deviceSerial,
            'result' => $this->result,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'message' => $this->message,
            'is_cancelled' => $this->isCancelled,
            'timestamp' => $this->timestamp->toIso8601String(),
            'employee_id' => $this->employeeId,
            'backup_num' => $this->backupNum,
            'image' => $this->image,
            'raw_data' => $this->rawData,
        ];
    }

    /** Get human-readable status label */
    public function getStatusLabel(): string
    {
        if ($this->isCancelled) {
            return 'لغو شده';
        }

        return match ($this->status) {
            1 => 'اولین اسکن اثر انگشت',
            2 => 'دومین اسکن اثر انگشت',
            3 => 'سومین اسکن اثر انگشت',
            100 => 'ثبت موفقیت‌آمیز',
            default => 'نامشخص',
        };
    }

    /** Check if registration is in progress (any step 1, 2, or 3) */
    public function isInProgress(): bool
    {
        return in_array($this->status, [1, 2, 3]) && ! $this->isCancelled;
    }

    /** Check if registration is complete (status 100) */
    public function isComplete(): bool
    {
        return $this->status === 100 && ! $this->isCancelled;
    }

    /** Check if this is first scan */
    public function isFirstScan(): bool
    {
        return $this->status === 1 && ! $this->isCancelled;
    }

    /** Check if this is second scan */
    public function isSecondScan(): bool
    {
        return $this->status === 2 && ! $this->isCancelled;
    }

    /** Check if this is third scan */
    public function isThirdScan(): bool
    {
        return $this->status === 3 && ! $this->isCancelled;
    }

    /** Check if registration has failed */
    public function hasFailed(): bool
    {
        return $this->status < 0 || $this->isCancelled;
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
            1 => 'لطفاً اولین بار انگشت خود را روی سنسور قرار دهید',
            2 => 'لطفاً دومین بار انگشت خود را روی سنسور قرار دهید',
            3 => 'لطفاً سومین بار انگشت خود را روی سنسور قرار دهید',
            100 => 'اثر انگشت با موفقیت ثبت شد',
            default => 'در حال پردازش...',
        };
    }

    /**
     * Get progress percentage (0-100)
     * محاسبه درصد پیشرفت بر اساس status
     */
    public function getProgressPercentage(): int
    {
        if ($this->isCancelled) {
            return 0;
        }

        return match ($this->status) {
            1 => 33,  // اولین اسکن
            2 => 66,  // دومین اسکن
            3 => 90,  // سومین اسکن
            100 => 100, // کامل شده
            default => 0,
        };
    }
}
