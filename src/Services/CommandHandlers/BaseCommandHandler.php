<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Contracts\CommandHandlerInterface;
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * Base class for all command handlers
 */
abstract class BaseCommandHandler implements CommandHandlerInterface
{
    protected DataMapperInterface $mapper;

    public function __construct(DataMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    /** Build standard response */
    protected function buildResponse(string $command, bool $result, array $extra = []): array
    {
        return array_merge([
            'ret'       => $command,
            'result'    => $result,
            'cloudtime' => now()->format('Y-m-d H:i:s'),
        ], $extra);
    }

    /** Log handler activity */
    protected function log(string $message, array $context = []): void
    {
        Logger::debug("[{$this->getCommandName()}] {$message}" . '  :' . json_encode($context, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    }

    /** Get device serial from data */
    protected function getDeviceSerial(array $data): ?string
    {
        return $data['sn'] ?? null;
    }

    /**
     * بروزرسانی وضعیت دستور در دیتابیس
     *
     * @return bool آیا command وجود داشت و بروزرسانی شد؟
     */
    protected function updateCommandStatus(
        string $deviceSerial,
        string $commandName,
        bool $success,
        ?array $responseData = null
    ): bool {
        $deviceModel  = config('biometric-devices.models.device', Device::class);
        $commandModel = config('biometric-devices.models.device_command', DeviceCommand::class);

        // بررسی وجود Model ها
        if ( ! class_exists($deviceModel) || ! class_exists($commandModel)) {
            return false;
        }

        // پیدا کردن دستگاه
        $device = $deviceModel::where('serial', $deviceSerial)->first();

        if ( ! $device) {
            return false;
        }

        // پیدا کردن آخرین command با وضعیت Pending یا Sent
        $command = $commandModel::where('device_id', $device->id)
            ->where('command_name', $commandName)
            ->whereIn('status', [
                DeviceCommandStatusEnum::PENDING,
                DeviceCommandStatusEnum::SENT,
            ])
            ->latest('id')
            ->first();

        if ( ! $command) {
            return false;
        }

        // بروزرسانی وضعیت
        if ($success) {
            $command->markAsSuccess($responseData);
        } else {
            $errorMessage = $responseData['error'] ?? 'Command failed';
            $command->markAsFailed($errorMessage, $responseData);
        }

        return true;
    }
}
