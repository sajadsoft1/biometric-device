<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Carbon\Carbon;

/**
 * Command to set device time
 */
class SetTimeDTO
{
    public function __construct(
        public readonly Carbon $datetime,
    ) {}

    public function toArray(): array
    {
        return [
            'datetime' => $this->datetime->format('Y-m-d H:i:s'),
        ];
    }
}
