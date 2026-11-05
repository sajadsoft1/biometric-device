<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * یک روز تعطیل در دستور setholiday.
 *
 * startday و endday در پروتکل به شکل MM-DD هستند.
 */
class HolidayParameterDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $startDay,
        public readonly string $endDay,
        public readonly int $shift,
        public readonly int $dayZone,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Holiday name is required.');
        }

        if ( ! self::isMonthDay($startDay) || ! self::isMonthDay($endDay)) {
            throw new InvalidArgumentException('Holiday startDay and endDay must be MM-DD.');
        }
    }

    /** @return array{name: string, startday: string, endday: string, shift: int, dayzone: int} */
    public function toDeviceArray(): array
    {
        return [
            'name' => $this->name,
            'startday' => $this->startDay,
            'endday' => $this->endDay,
            'shift' => $this->shift,
            'dayzone' => $this->dayZone,
        ];
    }

    private static function isMonthDay(string $value): bool
    {
        return preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) === 1;
    }
}
