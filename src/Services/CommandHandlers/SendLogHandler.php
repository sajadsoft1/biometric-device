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
            $this->log('SendLogHandler:Attendance log from unregistered device', [
                'pure' => $data,
            ]);

            return $this->buildResponse('sendlog', false);
        }

        // تبدیل به DTO
        $attendanceDTO = $this->mapper->mapToAttendanceDTO($data);

        $this->log('SendLogHandler:Attendance log received', [
            'pure'   => $data,
            'mapped' => $attendanceDTO->toArray(),
        ]);

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
