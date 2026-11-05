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
        public readonly ?int $weekZone2 = null,
        public readonly ?int $weekZone3 = null,
        public readonly ?int $weekZone4 = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'week_zone' => $this->weekZone,
            'week_zone_2' => $this->weekZone2,
            'week_zone_3' => $this->weekZone3,
            'week_zone_4' => $this->weekZone4,
            'group' => $this->group,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
        ];
    }
}
