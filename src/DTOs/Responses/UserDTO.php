<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Complete user information with biometric data from device
 */
readonly class UserDTO
{
    public function __construct(
        public int $employeeId,
        public string $name,
        public bool $isAdmin,
        public BiometricType $biometricType,
        public ?string $biometricData,
        public string $deviceSerial,
        public ?int $cardNumber,
        public ?int $password,
        public bool $enabled,
        public ?int $shiftId,
        public ?string $department,
        public ?string $photoUrl,
        public ?int $fingerprintFlag,
        public ?int $fingerprintCount,
        public ?int $faceFlag,
        public array $rawData,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'is_admin' => $this->isAdmin,
            'biometric_type' => $this->biometricType->value,
            'has_biometric_data' => ! empty($this->biometricData),
            'biometric_data' => $this->biometricData,
            'device_serial' => $this->deviceSerial,
            'card_number' => $this->cardNumber,
            'password' => $this->password,
            'enabled' => $this->enabled,
            'shift_id' => $this->shiftId,
            'department' => $this->department,
            'photo_url' => $this->photoUrl,
            'fingerprint_flag' => $this->fingerprintFlag,
            'fingerprint_count' => $this->fingerprintCount,
            'face_flag' => $this->faceFlag,
        ];
    }
}
