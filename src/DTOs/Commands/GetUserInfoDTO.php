<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Command to get user info from device
 */
readonly class GetUserInfoDTO
{
    public function __construct(
        public int $employeeId,
        public ?BiometricType $biometricType = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'backupnum'   => $this->biometricType?->value,
        ];
    }
}
