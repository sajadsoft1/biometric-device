<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\DeviceConnected;
use Sajadsoft\BiometricDevices\Models\Device;

/**
 * Handler for device registration
 */
class RegisterDeviceHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $data['sn'] ?? null;

        if ( ! $serialNum) {
            $this->log('RegisterDeviceHandler:Registration failed - No serial number', [
                'pure' => $data,
            ]);

            return $this->buildResponse('reg', false);
        }

        // تبدیل به DTO
        $deviceInfoDTO = $this->mapper->mapToDeviceInfoDTO($data);

        // ذخیره خودکار در دیتابیس
        $device = $this->saveDeviceToDatabase($serialNum, $deviceInfoDTO, $connection);

        // پخش Event - برای اطلاع‌رسانی و پردازش‌های اضافی
        event(new DeviceConnected($serialNum, $deviceInfoDTO, $device));

        // پاسخ موفق
        return $this->buildResponse('reg', true);
    }

    /** ذخیره دستگاه در دیتابیس */
    protected function saveDeviceToDatabase(string $serial, $deviceInfoDTO, mixed $connection): Device
    {
        $deviceModel = config('biometric-devices.models.device', Device::class);

        // نکته: IP و Port بعداً توسط WebSocketDeviceDriver set می‌شوند
        // در این مرحله فقط device رو با اطلاعات پایه ذخیره می‌کنیم

        $device = $deviceModel::where('serial', $serial)->first();
        if ($device) {
            $device->is_online         = true;
            $device->last_connected_at = now();
            // update extra attributes
            $newExtraAttributes = array_merge($device->extra_attributes ?? [], [
                'firmware_version' => $deviceInfoDTO->firmwareVersion,
                'user_capacity'    => $deviceInfoDTO->userCapacity,
                'log_capacity'     => $deviceInfoDTO->logCapacity,
            ]);
            $device->updateDeviceInfo($newExtraAttributes);
        } else {
            $device = $deviceModel::create([
                'serial'            => $serial,
                'name'              => $deviceInfoDTO->modelName,
                'is_online'         => true,
                'last_connected_at' => now(),
                'extra_attributes'  => [
                    'firmware_version' => $deviceInfoDTO->firmwareVersion,
                    'user_capacity'    => $deviceInfoDTO->userCapacity,
                    'log_capacity'     => $deviceInfoDTO->logCapacity,
                    'device_type'      => $deviceInfoDTO->deviceType ?? 'unknown',
                ],
            ]);
        }

        return $device;
    }

    public function getCommandName(): string
    {
        return 'reg';
    }
}
