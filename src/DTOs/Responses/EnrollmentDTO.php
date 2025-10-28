<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * User enrollment info from device user list
 */
class EnrollmentDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly BiometricType $biometricType,
        public readonly bool $isAdmin,
        public readonly string $deviceSerial,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'        => $this->employeeId,
            'biometric_type'     => $this->biometricType->value,
            'biometric_category' => $this->biometricType->category(),
            'is_admin'           => $this->isAdmin,
            'device_serial'      => $this->deviceSerial,
        ];
    }
}
