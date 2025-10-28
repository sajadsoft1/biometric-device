<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

/**
 * Event dispatched when a command is sent to device
 */
class CommandSent
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceSerial,
        public readonly string $commandName,
        public readonly mixed $commandDTO,
        public readonly ?DeviceCommand $command = null,
    ) {}
}
