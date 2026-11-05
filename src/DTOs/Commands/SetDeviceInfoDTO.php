<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * دستور setdevinfo.
 *
 * فقط فیلدهای داده‌شده ارسال می‌شوند. پروتکل فیلدهای حذف‌شده را تغییر نمی‌دهد.
 */
class SetDeviceInfoDTO
{
    public function __construct(
        public readonly ?int $deviceId = null,
        public readonly ?int $language = null,
        public readonly ?int $volume = null,
        public readonly ?int $screensaver = null,
        public readonly ?int $verifyMode = null,
        public readonly int|bool|null $sleep = null,
        public readonly ?int $userFingerprintCount = null,
        public readonly ?int $logHint = null,
        public readonly ?int $reverifyTime = null,
    ) {
        $this->assertRange($volume, 0, 10, 'volume');
        $this->assertRange($screensaver, 0, 255, 'screensaver');
        $this->assertRange($userFingerprintCount, 1, 10, 'userfpnum');
        $this->assertRange($logHint, 0, null, 'loghint');
        $this->assertRange($reverifyTime, 0, 255, 'reverifytime');

        if (is_int($sleep)) {
            $this->assertRange($sleep, 0, null, 'sleep');
        }

        if ($this->toDevicePayload() === ['cmd' => 'setdevinfo']) {
            throw new InvalidArgumentException('setdevinfo requires at least one parameter.');
        }
    }

    public function toDevicePayload(): array
    {
        $payload = ['cmd' => 'setdevinfo'];
        $fields = [
            'deviceid' => $this->deviceId,
            'language' => $this->language,
            'volume' => $this->volume,
            'screensaver' => $this->screensaver,
            'verifymode' => $this->verifyMode,
            'sleep' => $this->sleep,
            'userfpnum' => $this->userFingerprintCount,
            'loghint' => $this->logHint,
            'reverifytime' => $this->reverifyTime,
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function assertRange(?int $value, int $min, ?int $max, string $field): void
    {
        if ($value === null) {
            return;
        }

        if ($value < $min || ($max !== null && $value > $max)) {
            $limit = $max === null ? "{$min} or greater" : "{$min}..{$max}";

            throw new InvalidArgumentException("{$field} must be {$limit}.");
        }
    }
}
