<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Carbon\Carbon;
use Sajadsoft\BiometricDevices\Enums\AttendanceEventType;
use Sajadsoft\BiometricDevices\Enums\VerificationMode;

/**
 * Standardized attendance/check-in data from device
 */
readonly class AttendanceDTO
{
    public function __construct(
        public int $employeeId,
        public string $employeeName,
        public Carbon $timestamp,
        public VerificationMode $verificationType,
        public bool $isCheckIn,
        public ?float $temperature,
        public string $deviceSerial,
        public ?string $photoBase64,
        public ?int $cardNumber,
        public ?int $password,
        public ?AttendanceEventType $eventType,
        public ?int $workCode,
        public array $rawData,
    ) {}

    /** Convert to array */
    public function toArray(): array
    {
        return [
            'employee_id'       => $this->employeeId,
            'employee_name'     => $this->employeeName,
            'timestamp'         => $this->timestamp->toIso8601String(),
            'verification_type' => $this->verificationType->value,
            'is_check_in'       => $this->isCheckIn,
            'temperature'       => $this->temperature,
            'device_serial'     => $this->deviceSerial,
            'photo'             => $this->photoBase64,
            'card_number'       => $this->cardNumber,
            'password'          => $this->password,
            'event_type'        => $this->eventType?->value,
            'event_type_label'  => $this->eventType?->description(),
            'work_code'         => $this->workCode,
            'row_data'          => $this->rawData,
        ];
    }
}
