<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

/**
 * Attendance Event Types
 * رویدادهای مختلف که در زمان ثبت حضور اتفاق می‌افتند
 */
enum AttendanceEventType: int
{
    case NORMAL_PUNCH = 0;           // تردد عادی
    case CHECK_IN     = 1;               // ورود
    case CHECK_OUT    = 2;              // خروج
    case BREAK_START  = 3;            // شروع استراحت
    case BREAK_END    = 4;              // پایان استراحت
    case OVERTIME_IN  = 5;            // شروع اضافه‌کاری
    case OVERTIME_OUT = 6;           // پایان اضافه‌کاری

    /** Get description in Persian */
    public function description(): string
    {
        return match ($this) {
            self::NORMAL_PUNCH => 'تردد عادی',
            self::CHECK_IN     => 'ورود',
            self::CHECK_OUT    => 'خروج',
            self::BREAK_START  => 'شروع استراحت',
            self::BREAK_END    => 'پایان استراحت',
            self::OVERTIME_IN  => 'شروع اضافه‌کاری',
            self::OVERTIME_OUT => 'پایان اضافه‌کاری',
        };
    }

    /** Create from int value with fallback */
    public static function tryFromValue(?int $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
