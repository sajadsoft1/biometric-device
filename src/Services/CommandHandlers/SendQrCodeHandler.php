<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Carbon\Carbon;
use Exception;
use Sajadsoft\BiometricDevices\DTOs\Responses\QrCodeDTO;
use Sajadsoft\BiometricDevices\Events\QrCodeReceived;

/**
 * Handler for QR code scan from device
 * وقتی دستگاه QR code را اسکن می‌کند این handler اجرا می‌شود
 */
class SendQrCodeHandler extends BaseCommandHandler
{
    /**
     * Handle QR code data from device
     *
     * بر اساس مستندات API، دستگاه فیلد 'record' را ارسال می‌کند (نه qrcode یا qr)
     *
     * @param array{
     *     cmd: string,
     *     sn: string,
     *     record?: string,    // محتوای QR code (طبق مستندات)
     *     qrcode?: string,    // fallback برای سازگاری
     *     qr?: string,        // fallback برای سازگاری
     *     enrollid?: int,
     *     time?: string,
     * } $data
     */
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);

        if ( ! $serialNum) {
            $this->log('SendQrCodeHandler:QR code from unregistered device', [
                'pure' => $data,
            ]);

            return $this->buildResponse('sendqrcode', false);
        }

        // استخراج محتوای QR code - طبق مستندات API فیلد 'record' است
        $qrCodeContent = $data['record'] ?? $data['qrcode'] ?? $data['qr'] ?? null;

        if ( ! $qrCodeContent) {
            $this->log('SendQrCodeHandler:Empty QR code received', [
                'pure' => $data,
            ]);

            return $this->buildResponse('sendqrcode', false);
        }

        // تبدیل به DTO
        $qrCodeDTO = new QrCodeDTO(
            qrCodeData: $qrCodeContent,
            deviceSerial: $serialNum,
            timestamp: isset($data['time']) ? $this->parseTimestamp($data['time']) : Carbon::now(),
            employeeId: isset($data['enrollid']) ? (int) $data['enrollid'] : null,
            rawData: $data
        );

        $this->log('SendQrCodeHandler:QR code scanned', [
            'pure' => $data,
            'mapped' => $qrCodeDTO->toArray(),
        ]);

        // پخش Event
        event(new QrCodeReceived($qrCodeDTO));

        // پاسخ پیش‌فرض - Custom Handler در app layer می‌تواند این را override کند
        // طبق مستندات API باید شامل: access, enrollid, username, message, voice باشد
        return $this->buildResponse('sendqrcode', true, [
            'access' => 0, // پیش‌فرض: دسترسی نداره (Custom Handler باید تغییر بده)
            'enrollid' => $qrCodeDTO->employeeId,
            'message' => 'QR code received',
            'voice' => 'QR code received',
        ]);
    }

    public function getCommandName(): string
    {
        return 'sendqrcode';
    }

    /** Parse timestamp from device format */
    protected function parseTimestamp(?string $time): Carbon
    {
        if ( ! $time) {
            return Carbon::now();
        }

        try {
            return Carbon::parse($time);
        } catch (Exception $e) {
            $this->log('SendQrCodeHandler:Failed to parse timestamp', [
                'time' => $time,
                'error' => $e->getMessage(),
            ]);

            return Carbon::now();
        }
    }
}
