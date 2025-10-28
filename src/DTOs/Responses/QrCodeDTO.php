<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Responses;

use Carbon\Carbon;

/**
 * QR Code data received from device
 * دیتای QR Code دریافت شده از دستگاه
 */
readonly class QrCodeDTO
{
    public function __construct(
        public string $qrCodeData,       // محتوای QR code
        public string $deviceSerial,     // شماره سریال دستگاه
        public Carbon $timestamp,        // زمان اسکن
        public ?int $employeeId,         // شناسه کارمند (اگر به کاربر مرتبط باشد)
        public array $rawData,           // داده‌های خام دستگاه
    ) {}

    /** Convert to array */
    public function toArray(): array
    {
        return [
            'qr_code_data' => $this->qrCodeData,
            'device_serial' => $this->deviceSerial,
            'timestamp' => $this->timestamp->toIso8601String(),
            'employee_id' => $this->employeeId,
            'raw_data' => $this->rawData,
        ];
    }
}
