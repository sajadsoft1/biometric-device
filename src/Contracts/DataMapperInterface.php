<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Contracts;

use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetLogsDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetUserInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetDeviceLockDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetTimeDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\AttendanceDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\DeviceInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\EnrollmentDTO;
use Sajadsoft\BiometricDevices\DTOs\Responses\UserDTO;

/**
 * Interface for mapping between device-specific format and standardized DTOs
 */
interface DataMapperInterface
{
    // ============================================
    // FROM Device TO DTO (Responses)
    // ============================================

    /** Map device attendance data to AttendanceDTO */
    public function mapToAttendanceDTO(array $data): AttendanceDTO;

    /** Map device user data to UserDTO */
    public function mapToUserDTO(array $data): UserDTO;

    /** Map device enrollment data to EnrollmentDTO */
    public function mapToEnrollmentDTO(array $data): EnrollmentDTO;

    /** Map device info to DeviceInfoDTO */
    public function mapToDeviceInfoDTO(array $data): DeviceInfoDTO;

    // ============================================
    // FROM DTO TO Device Format (Commands)
    // ============================================

    // User Management
    /** Map AddUserDTO to device command format */
    public function mapAddUserCommand(AddUserDTO $dto): array;

    /** Map DeleteUserDTO to device command format */
    public function mapDeleteUserCommand(DeleteUserDTO $dto): array;

    /** Map GetUserInfoDTO to device command format */
    public function mapGetUserInfoCommand(GetUserInfoDTO $dto): array;

    // Device Control
    /** Map OpenDoorDTO to device command format */
    public function mapOpenDoorCommand(OpenDoorDTO $dto): array;

    /** Map SetTimeDTO to device command format */
    public function mapSetTimeCommand(SetTimeDTO $dto): array;

    // Access Control
    /** Map SetUserAccessDTO to device command format */
    public function mapSetUserAccessCommand(SetUserAccessDTO $dto): array;

    /** Map SetDeviceLockDTO to device command format */
    public function mapSetDeviceLockCommand(SetDeviceLockDTO $dto): array;

    // Attendance Logs
    /** Map GetLogsDTO to device command format */
    public function mapGetLogsCommand(GetLogsDTO $dto, string $commandName): array;
}
