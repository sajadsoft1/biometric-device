<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sajadsoft\BiometricDevices\DTOs\Responses\DeviceInfoDTO;
use Sajadsoft\BiometricDevices\Models\Device;

/**
 * Event dispatched when a device connects
 */
class DeviceConnected
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceSerial,
        public readonly DeviceInfoDTO $deviceInfo,
        public readonly ?Device $device = null,
    ) {}
}
