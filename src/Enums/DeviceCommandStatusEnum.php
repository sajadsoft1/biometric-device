<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum DeviceCommandStatusEnum: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function title(): string
    {
        return match ($this) {
            self::PENDING => 'PENDING',
            self::SENT => 'SENT',
            self::SUCCESS => 'SUCCESS',
            self::FAILED => 'FAILED',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SENT => 'blue',
            self::SUCCESS => 'green',
            self::FAILED => 'red',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'در انتظار',
            self::SENT => 'ارسال شده',
            self::SUCCESS => 'موفق',
            self::FAILED => 'ناموفق',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'label' => $status->label(),
                'value' => $status->value,
                'color' => $status->color(),
            ],
            self::cases()
        );
    }
}
