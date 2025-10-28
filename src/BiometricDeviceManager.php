<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices;

use Illuminate\Contracts\Foundation\Application;
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Events\CommandSent;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

/**
 * Main manager class for biometric device operations
 */
class BiometricDeviceManager
{
    protected Application $app;

    protected DataMapperInterface $mapper;

    public function __construct(Application $app)
    {
        $this->app = $app;

        // Load mapper از config
        $mapperClass  = config('biometric-devices.mappers.zkteco-websocket');
        $this->mapper = new $mapperClass;
    }

    /** Send command to add user */
    public function addUser(string $deviceSerial, AddUserDTO $dto): void
    {
        $command = $this->mapper->mapAddUserCommand($dto);

        $this->sendRawCommand($deviceSerial, 'setuserinfo', $command, $dto);
    }

    /** Send command to delete user */
    public function deleteUser(string $deviceSerial, int $employeeId, ?BiometricType $biometricType = null): void
    {
        $command = [
            'cmd'       => 'deleteuser',
            'enrollid'  => $employeeId,
            'backupnum' => $biometricType?->value ?? 13, // 13 = delete all
        ];

        $this->sendRawCommand($deviceSerial, 'deleteuser', $command);
    }

    /** Send command to get user list */
    public function getUserList(string $deviceSerial, bool $startFromBeginning = true): void
    {
        $command = [
            'cmd' => 'getuserlist',
            'stn' => $startFromBeginning,
        ];

        $this->sendRawCommand($deviceSerial, 'getuserlist', $command);
    }

    /** Send command to open door */
    public function openDoor(string $deviceSerial, int $doorNumber = 1): void
    {
        $command = [
            'cmd'     => 'opendoor',
            'doornum' => $doorNumber,
        ];

        $this->sendRawCommand($deviceSerial, 'opendoor', $command);
    }

    /** Send command to get device info */
    public function getDeviceInfo(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'getdevinfo', ['cmd' => 'getdevinfo']);
    }

    /** Reboot device */
    public function reboot(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'reboot', ['cmd' => 'reboot']);
    }

    /** Initialize system (WARNING: deletes all data) */
    public function initSystem(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'initsys', ['cmd' => 'initsys']);
    }

    /** Set user access permissions */
    public function setUserAccess(string $deviceSerial, SetUserAccessDTO $dto): void
    {
        $command = $this->mapper->mapSetUserAccessCommand($dto);

        $this->sendRawCommand($deviceSerial, 'setuserlock', $command, $dto);
    }

    /**
     * Send raw command to device
     *
     * @param string     $deviceSerial شماره سریال دستگاه
     * @param string     $commandName  نام دستور
     * @param array      $params       پارامترهای دستور
     * @param mixed|null $dto          DTO مربوط به دستور (اختیاری)
     */
    protected function sendRawCommand(
        string $deviceSerial,
        string $commandName,
        array $params,
        mixed $dto = null
    ): void {
        // ذخیره خودکار دستور در دیتابیس (با params واقعی که به دستگاه میره)
        $command = $this->saveCommandToDatabase($deviceSerial, $commandName, $params);

        // ارسال Event برای اطلاع‌رسانی و پردازش‌های اضافی
        event(new CommandSent($deviceSerial, $commandName, $dto ?? (object) $params, $command));
    }

    /** ذخیره دستور در دیتابیس */
    protected function saveCommandToDatabase(string $deviceSerial, string $commandName, mixed $dto): ?DeviceCommand
    {
        $deviceModel  = config('biometric-devices.models.device', Device::class);
        $commandModel = config('biometric-devices.models.device_command', DeviceCommand::class);

        // بررسی وجود Model ها
        if ( ! class_exists($deviceModel) || ! class_exists($commandModel)) {
            return null;
        }

        // پیدا کردن دستگاه
        $device = $deviceModel::where('serial', $deviceSerial)->first();

        if ( ! $device) {
            Logger::debug('Device not found for command', [
                'serial'  => $deviceSerial,
                'command' => $commandName,
            ]);

            return null;
        }

        // بررسی تکراری نبودن (جلوگیری از duplicate)
        $recentCommand = $commandModel::where('device_id', $device->id)
            ->where('command_name', $commandName)
            ->where('created_at', '>=', now()->subSeconds(2))
            ->exists();

        if ($recentCommand) {
            Logger::debug('Duplicate command detected, skipping save', [
                'device_serial' => $deviceSerial,
                'command_name'  => $commandName,
            ]);

            return null;
        }

        // ذخیره command
        $command = $commandModel::create([
            'device_id'       => $device->id,
            'command_name'    => $commandName,
            'command_content' => json_encode($dto),
            'status'          => DeviceCommandStatusEnum::PENDING,
            'send_status'     => false,
        ]);

        Logger::debug('Command saved to database', [
            'command_id'    => $command->id,
            'device_serial' => $deviceSerial,
            'command_name'  => $commandName,
        ]);

        return $command;
    }

    /** Get mapper instance */
    public function getMapper(): DataMapperInterface
    {
        return $this->mapper;
    }

    /** Set custom mapper */
    public function setMapper(DataMapperInterface $mapper): void
    {
        $this->mapper = $mapper;
    }
}
