<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Sajadsoft\BiometricDevices\DTOs\Responses\QrCodeDTO;

/**
 * Event triggered when QR code is scanned on device
 * وقتی QR code روی دستگاه اسکن می‌شود این event trigger می‌شود
 */
class QrCodeReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public QrCodeDTO $qrCode,
    ) {}
}
