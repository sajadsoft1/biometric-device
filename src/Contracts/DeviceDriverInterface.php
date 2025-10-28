<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Contracts;

use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\DeleteUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetUserListDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO;

/**
 * Interface for device communication drivers
 */
interface DeviceDriverInterface
{
    /** Start the communication server */
    public function start(string $host, int $port): void;

    /** Send command to device using DTO */
    public function sendAddUser(string $deviceSerial, AddUserDTO $dto): bool;

    public function sendDeleteUser(string $deviceSerial, DeleteUserDTO $dto): bool;

    public function sendGetUserList(string $deviceSerial, GetUserListDTO $dto): bool;

    public function sendOpenDoor(string $deviceSerial, OpenDoorDTO $dto): bool;

    /** Send raw command */
    public function sendRawCommand(string $deviceSerial, string $commandName, array $params): bool;

    /** Check if device is connected */
    public function isDeviceConnected(string $deviceSerial): bool;

    /** Get list of connected devices */
    public function getConnectedDevices(): array;

    /** Stop the server */
    public function stop(): void;
}
