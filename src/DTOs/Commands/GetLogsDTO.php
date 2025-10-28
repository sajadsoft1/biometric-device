<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

/**
 * Command to get attendance logs from device
 */
class GetLogsDTO
{
    public function __construct(
        public readonly bool $startFromBeginning = true,
        public readonly bool $newLogsOnly = false,
    ) {}

    public function toArray(): array
    {
        return [
            'start_from_beginning' => $this->startFromBeginning,
            'new_logs_only'        => $this->newLogsOnly,
        ];
    }
}
