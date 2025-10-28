<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a device disconnects
 */
class DeviceDisconnected
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceSerial,
        public readonly Carbon $disconnectedAt,
    ) {}
}
