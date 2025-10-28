<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Illuminate\Support\Facades\Log;
use Sajadsoft\BiometricDevices\Events\AttendanceReceived;

/**
 * Handler for attendance log from device
 */
class SendLogHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        if ( ! $this->getDeviceSerial($data)) {
            $this->log('Attendance log from unregistered device');

            return $this->buildResponse('sendlog', false);
        }

        // تبدیل به DTO
        $attendanceDTO = $this->mapper->mapToAttendanceDTO($data);

        // پخش Event - کاربر مسئول ذخیره
        event(new AttendanceReceived($attendanceDTO));

        // پاسخ موفق
        return $this->buildResponse('sendlog', true);
    }

    public function getCommandName(): string
    {
        return 'sendlog';
    }
}
