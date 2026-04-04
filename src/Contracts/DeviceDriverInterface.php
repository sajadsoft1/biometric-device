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

/**
 * Interface for device communication drivers
 */
interface DeviceDriverInterface
{
    /** Start the communication server */
    public function start(string $host, int $port): void;

    // ============================================
    // User Management
    // ============================================

    /** Send add user command using DTO */
    public function sendAddUser(string $deviceSerial, AddUserDTO $dto): bool;

    /** Send delete user command using DTO */
    public function sendDeleteUser(string $deviceSerial, DeleteUserDTO $dto): bool;

    /** Send get user list command */
    public function sendGetUserList(string $deviceSerial, bool $startFromBeginning = true): bool;

    /** Send get user info command using DTO */
    public function sendGetUserInfo(string $deviceSerial, GetUserInfoDTO $dto): bool;

    // ============================================
    // Device Control
    // ============================================

    /** Send open door command using DTO */
    public function sendOpenDoor(string $deviceSerial, OpenDoorDTO $dto): bool;

    /** Send get device info command */
    public function sendGetDeviceInfo(string $deviceSerial): bool;

    /** Send reboot command */
    public function sendReboot(string $deviceSerial): bool;

    /** Send init system command (WARNING: deletes all data) */
    public function sendInitSystem(string $deviceSerial): bool;

    /** Send set time command using DTO */
    public function sendSetTime(string $deviceSerial, SetTimeDTO $dto): bool;

    // ============================================
    // Access Control
    // ============================================

    /** Send set user access command using DTO */
    public function sendSetUserAccess(string $deviceSerial, SetUserAccessDTO $dto): bool;

    /** Send set device lock command using DTO */
    public function sendSetDeviceLock(string $deviceSerial, SetDeviceLockDTO $dto): bool;

    // ============================================
    // Attendance Logs
    // ============================================

    /** Send get all logs command using DTO */
    public function sendGetAllLogs(string $deviceSerial, GetLogsDTO $dto): bool;

    /** Send get new logs command using DTO */
    public function sendGetNewLogs(string $deviceSerial, GetLogsDTO $dto): bool;

    // ============================================
    // Utilities
    // ============================================

    /** Send raw command */
    public function sendRawCommand(string $deviceSerial, string $commandName, array $params): bool;

    /** Check if device is connected */
    public function isDeviceConnected(string $deviceSerial): bool;

    /** Get list of connected devices */
    public function getConnectedDevices(): array;

    /** Stop the server */
    public function stop(): void;
}
