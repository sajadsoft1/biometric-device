<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Command to open lock on device
 */
class OpenLockDTO
{
    public function __construct(
        public readonly int $controllerPort = 1,
        public readonly int $lockerOpenTime = 5, // seconds
        public readonly ?DateTimeInterface $cloudTime = null,
    ) {
        $this->cloudTime ??= Carbon::now();
    }

    public function toArray(): array
    {
        return [
            'controller_port' => $this->controllerPort,
            'locker_open_time' => $this->lockerOpenTime,
            'cloud_time' => $this->cloudTime->format('Y-m-d H:i:s'),
        ];
    }
}
