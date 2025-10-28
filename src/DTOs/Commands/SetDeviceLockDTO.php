<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

/**
 * Command to lock/unlock device
 */
class SetDeviceLockDTO
{
    public function __construct(
        public readonly bool $locked,
    ) {}

    public function toArray(): array
    {
        return [
            'locked' => $this->locked,
        ];
    }
}
