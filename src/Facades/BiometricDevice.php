<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for easy access to BiometricDeviceManager
 *
 * @method static void                                                      addUser(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO $dto)
 * @method static void                                                      deleteUser(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO $dto)
 * @method static void                                                      getUserList(string $deviceSerial, ?\Sajadsoft\BiometricDevices\DTOs\Commands\GetUserListDTO $dto = null)
 * @method static void                                                      openDoor(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO $dto)
 * @method static void                                                      getDeviceInfo(string $deviceSerial)
 * @method static void                                                      reboot(string $deviceSerial)
 * @method static void                                                      initSystem(string $deviceSerial)
 * @method static void                                                      setUserAccess(string $deviceSerial, \Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO $dto)
 * @method static \Sajadsoft\BiometricDevices\Contracts\DataMapperInterface getMapper()
 * @method static void                                                      setMapper(\Sajadsoft\BiometricDevices\Contracts\DataMapperInterface $mapper)
 *
 * @see \Sajadsoft\BiometricDevices\BiometricDeviceManager
 */
class BiometricDevice extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'biometric-device';
    }
}
