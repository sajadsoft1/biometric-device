<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for easy access to BiometricDeviceManager
 *
 * @see \Sajadsoft\BiometricDevices\BiometricDeviceManager
 *
 * User Management
 * @method static void addUser(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO $dto)
 * @method static void deleteUser(string $deviceSerial, int $employeeId, ?\Sajadsoft\BiometricDevices\Enums\BiometricType $biometricType = null)
 * @method static void getUserList(string $deviceSerial, bool $startFromBeginning = true)
 * @method static void getUserInfo(string $deviceSerial, int $employeeId)
 *
 * Device Control
 * @method static void openDoor(string $deviceSerial, int $doorNumber = 1, int $duration = 5)
 * @method static void getDeviceInfo(string $deviceSerial)
 * @method static void reboot(string $deviceSerial)
 * @method static void initSystem(string $deviceSerial)
 * @method static void setTime(string $deviceSerial, \Carbon\Carbon $datetime)
 *
 * Access Control
 * @method static void setUserAccess(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO $dto)
 * @method static void setDeviceLock(string $deviceSerial, bool $locked)
 *
 * Attendance Logs
 * @method static void getAllLogs(string $deviceSerial, bool $startFromBeginning = true)
 * @method static void getNewLogs(string $deviceSerial, bool $startFromBeginning = true)
 *
 * Utilities
 * @method static \Sajadsoft\BiometricDevices\Contracts\DataMapperInterface getMapper()
 * @method static void                                                      setMapper(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface $mapper)
 */
class BiometricDevice extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'biometric-device';
    }
}
