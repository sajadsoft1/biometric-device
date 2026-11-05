<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * بدنه واقعی دستور setdevlock: پارامتر دسترسی دستگاه.
 *
 * کلید InputAlarm و mutiopen همان نام پروتکل هستند.
 *
 * @phpstan-type DayZone list<array{day: list<array{section: string}>}>
 * @phpstan-type WeekZone list<array{week: list<array{day: int}>}>
 * @phpstan-type LockGroup list<array{group: int}>
 */
class SetAccessParameterDTO
{
    /**
     * @param DayZone $dayZone
     * @param WeekZone $weekZone
     * @param LockGroup $lockGroup
     */
    public function __construct(
        public readonly int $openDelay,
        public readonly int $doorSensor,
        public readonly int $alarmDelay,
        public readonly int $threat,
        public readonly int $inputAlarm,
        public readonly int $antiPassback,
        public readonly int $interlock,
        public readonly int $multiOpen,
        public readonly int $tryAlarm,
        public readonly int $tamper,
        public readonly int $wiegandFormat,
        public readonly int $wiegandOutput,
        public readonly int $cardOutput,
        public readonly array $dayZone,
        public readonly array $weekZone,
        public readonly array $lockGroup,
    ) {
        $this->assertDayZone($dayZone);
        $this->assertWeekZone($weekZone);
        $this->assertLockGroup($lockGroup);
    }

    public function toDevicePayload(): array
    {
        return [
            'cmd' => 'setdevlock',
            'opendelay' => $this->openDelay,
            'doorsensor' => $this->doorSensor,
            'alarmdelay' => $this->alarmDelay,
            'threat' => $this->threat,
            'InputAlarm' => $this->inputAlarm,
            'antpass' => $this->antiPassback,
            'interlock' => $this->interlock,
            'mutiopen' => $this->multiOpen,
            'tryalarm' => $this->tryAlarm,
            'tamper' => $this->tamper,
            'wgformat' => $this->wiegandFormat,
            'wgoutput' => $this->wiegandOutput,
            'cardoutput' => $this->cardOutput,
            'dayzone' => $this->dayZone,
            'weekzone' => $this->weekZone,
            'lockgroup' => $this->lockGroup,
        ];
    }

    /** @param list<mixed> $dayZone */
    private function assertDayZone(array $dayZone): void
    {
        foreach ($dayZone as $zone) {
            if ( ! is_array($zone) || ! isset($zone['day']) || ! is_array($zone['day'])) {
                throw new InvalidArgumentException('Each dayzone item must contain a day list.');
            }

            foreach ($zone['day'] as $section) {
                if ( ! is_array($section) || ! isset($section['section']) || ! is_string($section['section'])) {
                    throw new InvalidArgumentException('Each day section must be a string.');
                }

                if (preg_match('/^\d{2}:\d{2}~\d{2}:\d{2}$/', $section['section']) !== 1) {
                    throw new InvalidArgumentException('Day section must match HH:MM~HH:MM.');
                }
            }
        }
    }

    /** @param list<mixed> $weekZone */
    private function assertWeekZone(array $weekZone): void
    {
        foreach ($weekZone as $zone) {
            if ( ! is_array($zone) || ! isset($zone['week']) || ! is_array($zone['week'])) {
                throw new InvalidArgumentException('Each weekzone item must contain a week list.');
            }

            foreach ($zone['week'] as $day) {
                if ( ! is_array($day) || ! isset($day['day']) || ! is_int($day['day'])) {
                    throw new InvalidArgumentException('Each week day must be an integer dayzone index.');
                }
            }
        }
    }

    /** @param list<mixed> $lockGroup */
    private function assertLockGroup(array $lockGroup): void
    {
        foreach ($lockGroup as $group) {
            if ( ! is_array($group) || ! isset($group['group']) || ! is_int($group['group'])) {
                throw new InvalidArgumentException('Each lockgroup item must contain an integer group.');
            }
        }
    }
}
