<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum VerificationMode: string
{
    case FINGERPRINT = 'fingerprint';
    case FACE = 'face';
    case CARD = 'card';
    case PASSWORD = 'password';
    case PHOTO = 'photo';
    case QR_CODE = 'qr_code';
    case UNKNOWN = 'unknown';

    /** Create from device mode value */
    public static function fromDeviceMode(int $mode): self
    {
        return match (true) {
            $mode === 8 => self::FACE,       // checked
            $mode === 3 => self::CARD,       // checked
            $mode === 1 => self::FINGERPRINT, // checked
            $mode === 10 => self::PASSWORD,
            $mode === 13 => self::QR_CODE, // checked
            $mode === 50 => self::PHOTO,
            default => self::UNKNOWN,
        };
    }

    /** Get human-readable label */
    public function label(): string
    {
        return match ($this) {
            self::FINGERPRINT => 'Fingerprint',
            self::FACE => 'Face',
            self::CARD => 'Card',
            self::PASSWORD => 'Password',
            self::PHOTO => 'Photo',
            self::QR_CODE => 'QR Code',
            self::UNKNOWN => 'Unknown',
        };
    }
}
