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
        public readonly ?string $name = null,
        public readonly ?int $fingerprintFlag = null,
        public readonly ?int $fingerprintCount = null,
        public readonly ?int $faceFlag = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'        => $this->employeeId,
            'name'               => $this->name,
            'biometric_type'     => $this->biometricType->value,
            'biometric_category' => $this->biometricType->category(),
            'is_admin'           => $this->isAdmin,
            'device_serial'      => $this->deviceSerial,
            'fingerprint_flag'   => $this->fingerprintFlag,
            'fingerprint_count'  => $this->fingerprintCount,
            'face_flag'          => $this->faceFlag,
        ];
    }
}
