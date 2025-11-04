<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use Sajadsoft\BiometricDevices\Enums\BiometricType;

/**
 * Command to start fingerprint enrollment on device
 *
 * این دستور به دستگاه می‌گوید که وارد حالت ثبت اثر انگشت شود
 * و منتظر بماند تا کاربر اثر انگشت خود را روی سنسور قرار دهد
 */
class EnrollFingerprintDTO
{
    public function __construct(
        public readonly int $employeeId,              // شناسه کاربر
        public readonly BiometricType $biometricType, // نوع بیومتریک (fingerprint slot)
        public readonly int $flag = 2,                // پرچم دستور (پیش‌فرض 2)
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'    => $this->employeeId,
            'biometric_type' => $this->biometricType->value,
            'flag'           => $this->flag,
        ];
    }
}
