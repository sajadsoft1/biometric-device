<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Command to delete user from device
 */
readonly class DeleteUserDTO
{
    public function __construct(
        public int $employeeId,
        public ?BiometricType $biometricType = null, // null = delete all
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'biometric_type' => $this->biometricType?->value,
        ];
    }
}
