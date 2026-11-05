<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

/**
 * پاسخ دستورهایی که دستگاه با ret و result برمی‌گرداند.
 *
 * getquestionnaire و getholiday اگر count باقی‌مانده داشته باشند، بسته بعدی با stn=false درخواست می‌شود.
 */
class ResultResponseHandler extends BaseCommandHandler
{
    public function handle(array $data, $connection): ?array
    {
        $serialNum = $this->getDeviceSerial($data);
        $commandName = (string) ($data['ret'] ?? '');

        if ( ! $serialNum || $commandName === '') {
            return null;
        }

        $success = $this->isSuccessful($data);

        $this->log('ResultResponseHandler:Command response received', [
            'command' => $commandName,
            'pure' => $data,
            'success' => $success,
        ]);

        $this->updateCommandStatus($serialNum, $commandName, $success, $data);

        event(new CommandResponseReceived(
            deviceSerial: $serialNum,
            commandName: $commandName,
            success: $success,
            responseData: $data
        ));

        if ($success && $this->hasRemainingPackets($commandName, $data)) {
            return [
                'cmd' => $commandName,
                'stn' => false,
            ];
        }

        return null;
    }

    public function getCommandName(): string
    {
        return 'result';
    }

    private function isSuccessful(array $data): bool
    {
        if ( ! array_key_exists('result', $data)) {
            return true;
        }

        return $data['result'] === true || $data['result'] === 1;
    }

    private function hasRemainingPackets(string $commandName, array $data): bool
    {
        if ( ! in_array($commandName, ['getquestionnaire', 'getholiday'], true)) {
            return false;
        }

        return (int) ($data['count'] ?? 0) > 0;
    }
}
