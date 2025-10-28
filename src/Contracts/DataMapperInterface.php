<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Contracts;

use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
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

    /** Map AddUserDTO to device command format */
    public function mapAddUserCommand(AddUserDTO $dto): array;

    /** Map SetUserAccessDTO to device command format */
    public function mapSetUserAccessCommand(SetUserAccessDTO $dto): array;
}
