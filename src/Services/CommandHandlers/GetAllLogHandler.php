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
    /**
     * Undocumented function
     *
     * @param array{
     * ret?: string,
     * result?: bool,
     * sn: string,
     * count?: int,
     * from?: int,
     * to?: int,
     *  record: array<array{
     *      enrollid: int,
     *      name: string,
     *      time: string,
     *      mode: int,
     *      inout: int,
     *      event: int,
     *  }>,
     *  count?: int,
     *  remaining?: int,
     * } $data
     * @param mixed $connection
     */
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            return null;
        }

        $count = 0;
        // پردازش هر رکورد
        if (isset($data['record']) && is_array($data['record'])) {
            foreach ($data['record'] as $record) {
                $record['sn']  = $serialNum;
                $attendanceDTO = $this->mapper->mapToAttendanceDTO($record);
                event(new AttendanceReceived($attendanceDTO));
            }

            $count = count($data['record']);
        }

        $remainingCount = $data['count'] ?? 0;

        $this->log('GetAllLogHandler:Attendance logs received', [
            'pure'      => $data,
            'count'     => $count,
            'remaining' => $remainingCount,
        ]);

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
