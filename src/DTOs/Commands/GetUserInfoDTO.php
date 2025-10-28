<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

/**
 * Command to get user info from device
 */
class GetUserInfoDTO
{
    public function __construct(
        public readonly int $employeeId,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
        ];
    }
}
