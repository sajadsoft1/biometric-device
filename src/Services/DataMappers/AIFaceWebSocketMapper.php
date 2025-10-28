<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\DataMappers;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\AttendanceDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\DeviceInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\EnrollmentDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\UserDTO;
use Sajadsoft\BiometricDevices\Enums\AttendanceEventType;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * Data mapper for AIFace WebSocket protocol devices
 */
class AIFaceWebSocketMapper extends AbstractDataMapper
{
    // ============================================
    // FROM Device TO DTO
    // ============================================

    /**
     * @param array{
     *     cmd:string,
     *     sn:string,
     *     count:int,
     *     logindex:int,
     *     record: array{
     *      enrollid: int,
     *      name: string,
     *      time: string,
     *      mode:int,
     *      inout:int,
     *      event:int
     *  }[]
     * } $data
     */
    public function mapToAttendanceDTO(array $data): AttendanceDTO
    {
        $record   = $data['record'][0] ?? [];
        $enrollId = $record['enrollid'] ?? 0;

        return new AttendanceDTO(
            employeeId: $enrollId,
            employeeName: $record['name'] ?? "Employee {$enrollId}",
            timestamp: $this->parseTimestamp($record['time'] ?? null),
            verificationType: $this->mapVerificationMode($record['mode'] ?? 0),
            isCheckIn: (int) Arr::get($record, 'inout', 0) === 0,
            deviceSerial: $data['sn'] ?? '',
            photoBase64: $record['image'] ?? null,
            eventType: AttendanceEventType::tryFromValue($record['event'] ?? null),
            rawData: $data,
        );
    }

    public function mapToUserDTO(array $data): UserDTO
    {
        $enrollId = $data['enrollid'] ?? 0;
        $admin    = $this->parseBoolean($data['admin'] ?? 0);
        $enabled  = $this->parseBoolean($data['enable'] ?? 1);

        // Convert record to string (can be int for password/card or string for biometric data)
        $biometricData = null;
        if (isset($data['record'])) {
            $biometricData = (string) $data['record'];
        }

        return new UserDTO(
            employeeId: $enrollId,
            name: $data['name'] ?? "Employee {$enrollId}",
            isAdmin: $admin,
            biometricType: $this->mapBiometricType($data['backupnum'] ?? 0),
            biometricData: $biometricData,
            deviceSerial: $data['sn'] ?? '',
            cardNumber: isset($data['card']) ? (int) $data['card'] : null,
            password: isset($data['pwd']) ? (int) $data['pwd'] : null,
            enabled: $enabled,
            shiftId: isset($data['shiftid']) ? (int) $data['shiftid'] : null,
            rawData: $data,
        );
    }

    public function mapToEnrollmentDTO(array $data): EnrollmentDTO
    {
        $admin = $this->parseBoolean($data['admin'] ?? 0);

        return new EnrollmentDTO(
            employeeId: $data['enrollid'] ?? 0,
            biometricType: $this->mapBiometricType($data['backupnum'] ?? 0),
            isAdmin: $admin,
            deviceSerial: $data['sn'] ?? '',
        );
    }

    /**
     * @param array{
     *     cmd:string,
     *     sn:string,
     *     devinfo?: array{
     *         modelname?: string,
     *         usersize?: int,
     *         facesize?: int,
     *         fpsize?: int,
     *         cardsize?: int,
     *         pwdsize?: int,
     *         logsize?: int,
     *         useduser?: int,
     *         usedface?: int,
     *         usedfp?: int,
     *         usedcard?: int,
     *         usedpwd?: int,
     *         usedlog?: int,
     *         usednewlog?: int,
     *         usedrtlog?: int,
     *         netinuse?: int,
     *         usb4g?: int,
     *         fpalgo?: string,
     *         firmware?: string,
     *         time?: string,
     *         intercom?: int,
     *         floors?: int,
     *         charid?: int,
     *         useosdp?: int,
     *         dislanguage?: int,
     *         mac?: string,
     *     }
     * } $data
     */
    public function mapToDeviceInfoDTO(array $data): DeviceInfoDTO
    {
        Logger::debug('Mapping device info to DTO', ['data' => $data]);
        $devInfo = $data['devinfo'] ?? $data;

        return new DeviceInfoDTO(
            serialNumber: $data['sn'] ?? '',
            modelName: $devInfo['modelname'] ?? 'Unknown',
            firmwareVersion: $devInfo['firmware'] ?? 'Unknown',
            userCapacity: $devInfo['usersize'] ?? 0,
            logCapacity: $devInfo['logsize'] ?? 0,
            usedUsers: $devInfo['useduser'] ?? 0,
            usedLogs: $devInfo['usedlog'] ?? 0,
            capabilities: $this->extractCapabilities($devInfo),
            rawData: $data,
        );
    }

    protected function extractCapabilities(array $devInfo): array
    {
        return [
            'face_capacity'        => $devInfo['facesize'] ?? 0,
            'fingerprint_capacity' => $devInfo['fpsize'] ?? 0,
            'card_capacity'        => $devInfo['cardsize'] ?? 0,
            'used_face'            => $devInfo['usedface'] ?? 0,
            'used_fingerprint'     => $devInfo['usedfp'] ?? 0,
            'used_card'            => $devInfo['usedcard'] ?? 0,
        ];
    }

    // ============================================
    // FROM DTO TO Device Format
    // ============================================

    // User Management

    public function mapAddUserCommand(AddUserDTO $dto): array
    {
        $backupNum = $this->biometricTypeToDeviceValue($dto->biometricType);

        $command = [
            'cmd'       => 'setuserinfo',
            'enrollid'  => $dto->employeeId,
            'name'      => $dto->name,
            'backupnum' => $backupNum,
            'admin'     => $dto->isAdmin ? 1 : 0,
        ];

        // For password and card, record is numeric
        if ($backupNum == 10 || $backupNum == 11) {
            $command['record'] = (int) $dto->biometricData;
        } else {
            $command['record'] = $dto->biometricData;
        }

        return $command;
    }

    public function mapDeleteUserCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO $dto): array
    {
        $command = [
            'cmd'      => 'deleteuser',
            'enrollid' => $dto->employeeId,
        ];

        // If biometric type is specified, delete only that type
        // Otherwise, 13 = delete all biometric data
        $command['backupnum'] = $dto->biometricType?->value ?? 13;

        return $command;
    }

    public function mapGetUserInfoCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\GetUserInfoDTO $dto): array
    {
        return [
            'cmd'      => 'getuserinfo',
            'enrollid' => $dto->employeeId,
        ];
    }

    // Device Control

    public function mapOpenDoorCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO $dto): array
    {
        return [
            'cmd'      => 'opendoor',
            'doornum'  => $dto->doorNumber,
            'duration' => $dto->duration,
        ];
    }

    public function mapSetTimeCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\SetTimeDTO $dto): array
    {
        return [
            'cmd'  => 'settime',
            'time' => $dto->datetime->format('Y-m-d H:i:s'),
        ];
    }

    // Access Control

    public function mapSetUserAccessCommand(SetUserAccessDTO $dto): array
    {
        return [
            'cmd'    => 'setuserlock',
            'count'  => 1,
            'record' => [
                [
                    'enrollid'  => $dto->employeeId,
                    'weekzone'  => $dto->weekZone,
                    'group'     => $dto->group,
                    'starttime' => $dto->startDate->format('Y-m-d') . ' 00:00:00',
                    'endtime'   => $dto->endDate->format('Y-m-d') . ' 00:00:00',
                ],
            ],
        ];
    }

    public function mapSetDeviceLockCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\SetDeviceLockDTO $dto): array
    {
        return [
            'cmd'    => 'setdevlock',
            'locked' => $dto->locked ? 1 : 0,
        ];
    }

    // Attendance Logs

    public function mapGetLogsCommand(\Sajadsoft\BiometricDevices\DTOs\Commands\GetLogsDTO $dto, string $commandName): array
    {
        return [
            'cmd' => $commandName, // 'getalllog' or 'getnewlog'
            'stn' => $dto->startFromBeginning ? 1 : 0,
        ];
    }

    /** Parse timestamp from various formats */
    protected function parseTimestamp(?string $time): Carbon
    {
        if ( ! $time) {
            return now();
        }

        try {
            if (is_numeric($time)) {
                return Carbon::createFromTimestamp($time);
            }

            return Carbon::parse($time);
        } catch (Exception $e) {
            return now();
        }
    }
}
