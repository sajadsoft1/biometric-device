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
     * @param array{
     *     cmd: string,
     *     sn: string,
     *     qrcode?: string,
     *     qr?: string,
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

        // استخراج محتوای QR code
        $qrCodeContent = $data['qrcode'] ?? $data['qr'] ?? null;

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
            'pure'   => $data,
            'mapped' => $qrCodeDTO->toArray(),
        ]);

        // پخش Event
        event(new QrCodeReceived($qrCodeDTO));

        // پاسخ موفق
        return $this->buildResponse('sendqrcode', true);
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
                'time'  => $time,
                'error' => $e->getMessage(),
            ]);

            return Carbon::now();
        }
    }
}
