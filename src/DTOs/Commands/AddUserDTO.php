<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Command to add/update user on device
 */
class AddUserDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly BiometricType $biometricType,
        public readonly string $biometricData,
        public readonly bool $isAdmin = false,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'    => $this->employeeId,
            'name'           => $this->name,
            'biometric_type' => $this->biometricType->value,
            'is_admin'       => $this->isAdmin,
        ];
    }
}
