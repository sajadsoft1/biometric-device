<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sajadsoft\BiometricDevices\DTOs\Responses\UserDTO;

/**
 * Event dispatched when user info is received from device
 */
class UserInfoReceived
{
    use Dispatchable;

    public function __construct(
        public readonly UserDTO $user,
    ) {}
}
