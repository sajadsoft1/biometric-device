<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * دستور setholiday. پروتکل حداکثر ۳۰ تعطیلی می‌پذیرد.
 */
class SetHolidayDTO
{
    /**
     * @param list<HolidayParameterDTO> $holidays
     */
    public function __construct(
        public readonly array $holidays,
    ) {
        if (count($holidays) > 30) {
            throw new InvalidArgumentException('setholiday accepts at most 30 holidays.');
        }

        foreach ($holidays as $holiday) {
            if ( ! $holiday instanceof HolidayParameterDTO) {
                throw new InvalidArgumentException('Each holiday must be a HolidayParameterDTO.');
            }
        }
    }

    /** @return array{cmd: string, holidays: list<array{name: string, startday: string, endday: string, shift: int, dayzone: int}>} */
    public function toDevicePayload(): array
    {
        return [
            'cmd' => 'setholiday',
            'holidays' => array_map(
                fn (HolidayParameterDTO $holiday): array => $holiday->toDeviceArray(),
                $this->holidays
            ),
        ];
    }
}
