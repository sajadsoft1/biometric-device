<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a command response is received
 */
class CommandResponseReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceSerial,
        public readonly string $commandName,
        public readonly bool $success,
        public readonly array $responseData,
        public readonly ?int $commandId = null,
    ) {}
}
