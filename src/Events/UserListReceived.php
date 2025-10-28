<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when user list is received from device
 */
class UserListReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $deviceSerial,
        public readonly array $enrollments, // array of EnrollmentDTO
        public readonly int $totalCount,
        public readonly bool $hasMore,
    ) {}
}
