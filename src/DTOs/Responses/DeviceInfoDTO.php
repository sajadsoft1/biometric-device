<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

/**
 * Device information and capabilities
 */
class DeviceInfoDTO
{
    public function __construct(
        public readonly string $serialNumber,
        public readonly string $modelName,
        public readonly string $firmwareVersion,
        public readonly int $userCapacity,
        public readonly int $logCapacity,
        public readonly int $usedUsers,
        public readonly int $usedLogs,
        public readonly array $capabilities,
        public readonly array $rawData,
    ) {}

    public function toArray(): array
    {
        return [
            'serial_number' => $this->serialNumber,
            'model_name' => $this->modelName,
            'firmware_version' => $this->firmwareVersion,
            'user_capacity' => $this->userCapacity,
            'log_capacity' => $this->logCapacity,
            'used_users' => $this->usedUsers,
            'used_logs' => $this->usedLogs,
            'user_available' => $this->userCapacity - $this->usedUsers,
            'log_available' => $this->logCapacity - $this->usedLogs,
            'capabilities' => $this->capabilities,
        ];
    }
}
