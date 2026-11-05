<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Facades;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetAccessParameterDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetDeviceInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetHolidayDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetQuestionnaireDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserProfileDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Facade for easy access to BiometricDeviceManager.
 *
 * @see \Sajadsoft\BiometricDevices\BiometricDeviceManager
 *
 * User Management
 * @method static void addUser(string $deviceSerial, AddUserDTO $dto)
 * @method static void deleteUser(string $deviceSerial, int $employeeId, BiometricType|null $biometricType = null)
 * @method static void getUserList(string $deviceSerial, bool $startFromBeginning = true)
 * @method static void getUserInfo(string $deviceSerial, int $employeeId, BiometricType|null $backupnum = null)
 * @method static void getUsername(string $deviceSerial, int $employeeId)
 * @method static void enableUser(string $deviceSerial, int $employeeId, bool $enabled = true)
 * @method static void disableUser(string $deviceSerial, int $employeeId)
 * @method static void cleanUsers(string $deviceSerial)
 * @method static void setUserProfile(string $deviceSerial, SetUserProfileDTO $dto)
 * @method static void getUserProfile(string $deviceSerial, int $employeeId)
 *
 * Device Control
 * @method static void openDoor(string $deviceSerial, int $doorNumber = 1, int $duration = 5)
 * @method static void getDeviceInfo(string $deviceSerial)
 * @method static void setDeviceInfo(string $deviceSerial, SetDeviceInfoDTO $dto)
 * @method static void reboot(string $deviceSerial)
 * @method static void initSystem(string $deviceSerial)
 * @method static void setTime(string $deviceSerial, Carbon $datetime)
 * @method static void getTime(string $deviceSerial)
 *
 * Access Control
 * @method static void setUserAccess(string $deviceSerial, SetUserAccessDTO $dto)
 * @method static void getUserAccess(string $deviceSerial, int $employeeId)
 * @method static void deleteUserAccess(string $deviceSerial, int $employeeId)
 * @method static void cleanUserAccess(string $deviceSerial)
 * @method static void setDeviceLock(string $deviceSerial, bool $locked)
 * @method static void getAccessParameter(string $deviceSerial)
 * @method static void setAccessParameter(string $deviceSerial, SetAccessParameterDTO $dto)
 * @method static void getQuestionnaire(string $deviceSerial)
 * @method static void setQuestionnaire(string $deviceSerial, SetQuestionnaireDTO $dto)
 * @method static void getHoliday(string $deviceSerial)
 * @method static void setHoliday(string $deviceSerial, SetHolidayDTO $dto)
 *
 * Attendance Logs
 * @method static void getAllLogs(string $deviceSerial, bool $startFromBeginning = true, ?string $from = null, ?string $to = null)
 * @method static void getNewLogs(string $deviceSerial, bool $startFromBeginning = true)
 *
 * Utilities
 * @method static DataMapperInterface getMapper()
 * @method static void                setMapper(DataMapperInterface $mapper)
 */
class BiometricDevice extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'biometric-device';
    }
}
