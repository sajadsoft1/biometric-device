<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sajadsoft\BiometricDevices\DTOs\Responses\AttendanceDTO;

/**
 * Event dispatched when attendance data is received from device
 */
class AttendanceReceived
{
    use Dispatchable;

    public function __construct(
        public readonly AttendanceDTO $attendance,
    ) {}
}
