<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * Command to get attendance logs from device.
 *
 * from و to فقط برای getalllog هستند و هر دو با هم، به شکل Y-m-d، ارسال می‌شوند.
 */
class GetLogsDTO
{
    public function __construct(
        public readonly bool $startFromBeginning = true,
        public readonly bool $newLogsOnly = false,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
        if (($from === null) !== ($to === null)) {
            throw new InvalidArgumentException('getalllog from and to must be provided together.');
        }

        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new InvalidArgumentException("getalllog {$field} must be Y-m-d.");
            }
        }
    }

    public function toArray(): array
    {
        return [
            'start_from_beginning' => $this->startFromBeginning,
            'new_logs_only' => $this->newLogsOnly,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
