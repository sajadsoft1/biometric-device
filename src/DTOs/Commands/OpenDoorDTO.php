<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

/**
 * Command to open door on device
 */
class OpenDoorDTO
{
    public function __construct(
        public readonly int $doorNumber = 1,
        public readonly int $duration = 5, // seconds
    ) {}

    public function toArray(): array
    {
        return [
            'door_number' => $this->doorNumber,
            'duration'    => $this->duration,
        ];
    }
}
