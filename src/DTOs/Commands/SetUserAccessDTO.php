<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Carbon\Carbon;

/**
 * Command to set user access permissions
 */
class SetUserAccessDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly int $weekZone,
        public readonly int $group,
        public readonly Carbon $startDate,
        public readonly Carbon $endDate,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'week_zone'   => $this->weekZone,
            'group'       => $this->group,
            'start_date'  => $this->startDate->toDateString(),
            'end_date'    => $this->endDate->toDateString(),
        ];
    }
}
