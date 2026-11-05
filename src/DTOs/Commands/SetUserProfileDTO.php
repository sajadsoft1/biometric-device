<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * دستور setuserprofile.
 *
 * enrollid صفر اطلاعات عمومی است. بقیه شناسه‌ها اطلاعات شخصی هستند.
 * profile حداکثر ۲۰۰ بایت است.
 */
class SetUserProfileDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $profile,
    ) {
        if ($employeeId < 0) {
            throw new InvalidArgumentException('enrollid must be greater than or equal to 0.');
        }

        if (strlen($profile) > 200) {
            throw new InvalidArgumentException('User profile cannot exceed 200 bytes.');
        }
    }

    /** @return array{cmd: string, enrollid: int, profile: string} */
    public function toDevicePayload(): array
    {
        return [
            'cmd' => 'setuserprofile',
            'enrollid' => $this->employeeId,
            'profile' => $this->profile,
        ];
    }
}
