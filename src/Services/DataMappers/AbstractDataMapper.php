<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\DataMappers;

use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\Enums\BiometricType;
use Sajadsoft\BiometricDevices\Enums\VerificationMode;

/**
 * Base class for data mappers
 */
abstract class AbstractDataMapper implements DataMapperInterface
{
    /** Map device mode to VerificationMode enum */
    protected function mapVerificationMode(int $mode): VerificationMode
    {
        return VerificationMode::fromDeviceMode($mode);
    }

    /** Map device backup number to BiometricType enum */
    protected function mapBiometricType(int $backupNum): BiometricType
    {
        return BiometricType::fromValue($backupNum);
    }

    /** Parse boolean from device data */
    protected function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes']);
        }

        return (bool) $value;
    }

    /** Get device value from BiometricType */
    protected function biometricTypeToDeviceValue(BiometricType $type): int
    {
        return $type->value;
    }
}
