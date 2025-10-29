<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

/**
 * Attendance Event Types
 * Different events that occur when recording attendance
 */
enum AttendanceEventType: int
{
    case NORMAL_PUNCH = 0;           // Normal Punch
    case CHECK_IN     = 1;               // Check In
    case CHECK_OUT    = 2;              // Check Out
    case BREAK_START  = 3;            // Break Start
    case BREAK_END    = 4;              // Break End
    case OVERTIME_IN  = 5;            // Over Time In
    case OVERTIME_OUT = 6;           // Over Time Out

    /** Get title */
    public function title(): string
    {
        return match ($this) {
            self::NORMAL_PUNCH => 'Normal Punch',
            self::CHECK_IN     => 'Check In',
            self::CHECK_OUT    => 'Check Out',
            self::BREAK_START  => 'Break Start',
            self::BREAK_END    => 'Break End',
            self::OVERTIME_IN  => 'Over Time In',
            self::OVERTIME_OUT => 'Over Time Out',
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
