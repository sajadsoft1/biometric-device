<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Complete user information with biometric data from device
 */
class UserDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly bool $isAdmin,
        public readonly BiometricType $biometricType,
        public readonly ?string $biometricData,
        public readonly string $deviceSerial,
        public readonly ?int $cardNumber,
        public readonly ?int $password,
        public readonly bool $enabled,
        public readonly ?int $shiftId,
        public readonly ?string $department,
        public readonly ?string $photoUrl,
        public readonly ?int $fingerprintFlag,
        public readonly ?int $fingerprintCount,
        public readonly ?int $faceFlag,
        public readonly array $rawData,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'        => $this->employeeId,
            'name'               => $this->name,
            'is_admin'           => $this->isAdmin,
            'biometric_type'     => $this->biometricType->value,
            'has_biometric_data' => ! empty($this->biometricData),
            'device_serial'      => $this->deviceSerial,
            'card_number'        => $this->cardNumber,
            'password'           => $this->password,
            'enabled'            => $this->enabled,
            'shift_id'           => $this->shiftId,
            'department'         => $this->department,
            'photo_url'          => $this->photoUrl,
            'fingerprint_flag'   => $this->fingerprintFlag,
            'fingerprint_count'  => $this->fingerprintCount,
            'face_flag'          => $this->faceFlag,
        ];
    }
}
