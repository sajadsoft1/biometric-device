<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum DeviceModel: string
{
    case AI_FACE = 'aiface';
    case ZK_TECO = 'zkteco';

    /** Get the mapper class for this device model */
    public function getMapperClass(string $protocol = 'websocket'): string
    {
        return match ($this) {
            self::AI_FACE => config("biometric-devices.mappers.aiface-{$protocol}"),
            self::ZK_TECO => config("biometric-devices.mappers.zkteco-{$protocol}"),
        };
    }

    /** Get display label */
    public function label(): string
    {
        return match ($this) {
            self::AI_FACE => 'AI Face',
            self::ZK_TECO => 'ZK Teco',
        };
    }

    /** Get all options for select/dropdown */
    public static function options(): array
    {
        return array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
