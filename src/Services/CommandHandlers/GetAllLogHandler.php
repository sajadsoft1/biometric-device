<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * Handler for getalllog/getnewlog response
 */
class GetAllLogHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if (! $serialNum) {
            return null;
        }

        // پردازش هر رکورد
        if (isset($data['record']) && is_array($data['record'])) {
            foreach ($data['record'] as $record) {
                $record['sn'] = $serialNum;
                $attendanceDTO = $this->mapper->mapToAttendanceDTO($record);
                event(new AttendanceReceived($attendanceDTO));
            }

            $count = count($data['record']);
            $this->log("Attendance logs received: {$count}");
        }

        // پخش Event برای Command Status
        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: $this->getCommandName(),
            success: true,
            responseData: $data
        ));

        // اگر count > 0، ادامه دریافت
        $remainingCount = $data['count'] ?? 0;
        if ($remainingCount > 0) {
            return [
                'cmd' => $this->getCommandName(),
                'stn' => false,
            ];
        }

        return null;
    }

    public function getCommandName(): string
    {
        return 'getalllog';
    }
}
