<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum BiometricType: int
{
    case FINGERPRINT_0 = 0;
    case FINGERPRINT_1 = 1;
    case FINGERPRINT_2 = 2;
    case FINGERPRINT_3 = 3;
    case FINGERPRINT_4 = 4;
    case FINGERPRINT_5 = 5;
    case FINGERPRINT_6 = 6;
    case FINGERPRINT_7 = 7;
    case FINGERPRINT_8 = 8;
    case FINGERPRINT_9 = 9;
    case PASSWORD = 10;
    case CARD = 11;
    case FACE_0 = 20;
    case FACE_1 = 21;
    case FACE_2 = 22;
    case FACE_3 = 23;
    case FACE_4 = 24;
    case FACE_5 = 25;
    case FACE_6 = 26;
    case FACE_7 = 27;
    case PHOTO = 50;

    /** Get human-readable label */
    public function label(): string
    {
        return match ($this) {
            self::FINGERPRINT_0 => __('biometric.labels.fingerprint_1'),
            self::FINGERPRINT_1 => __('biometric.labels.fingerprint_2'),
            self::FINGERPRINT_2 => __('biometric.labels.fingerprint_3'),
            self::FINGERPRINT_3 => __('biometric.labels.fingerprint_4'),
            self::FINGERPRINT_4 => __('biometric.labels.fingerprint_5'),
            self::FINGERPRINT_5 => __('biometric.labels.fingerprint_6'),
            self::FINGERPRINT_6 => __('biometric.labels.fingerprint_7'),
            self::FINGERPRINT_7 => __('biometric.labels.fingerprint_8'),
            self::FINGERPRINT_8 => __('biometric.labels.fingerprint_9'),
            self::FINGERPRINT_9 => __('biometric.labels.fingerprint_10'),
            self::PASSWORD => __('biometric.labels.password'),
            self::CARD => __('biometric.labels.card'),
            self::FACE_0 => __('biometric.labels.face_1'),
            self::FACE_1 => __('biometric.labels.face_2'),
            self::FACE_2 => __('biometric.labels.face_3'),
            self::FACE_3 => __('biometric.labels.face_4'),
            self::FACE_4 => __('biometric.labels.face_5'),
            self::FACE_5 => __('biometric.labels.face_6'),
            self::FACE_6 => __('biometric.labels.face_7'),
            self::FACE_7 => __('biometric.labels.face_8'),
            self::PHOTO => __('biometric.labels.photo'),
        };
    }

    /** Get category (fingerprint, face, etc.) */
    public function category(): string
    {
        return match (true) {
            $this->value >= 0 && $this->value <= 9 => 'fingerprint',
            $this->value >= 20 && $this->value <= 27 => 'face',
            $this->value == 10 => 'password',
            $this->value == 11 => 'card',
            $this->value == 50 => 'photo',
            default => 'unknown',
        };
    }

    /** Safely create from value */
    public static function fromValue(int $value): self
    {
        return self::tryFrom($value) ?? self::FINGERPRINT_0;
    }

    /** Check if this is a fingerprint type */
    public function isFingerprint(): bool
    {
        return $this->value >= 0 && $this->value <= 9;
    }

    /** Check if this is a face recognition type */
    public function isFace(): bool
    {
        return $this->value >= 20 && $this->value <= 27;
    }
}
