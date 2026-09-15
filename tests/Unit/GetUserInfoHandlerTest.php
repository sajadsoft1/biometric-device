<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests\Unit;

use Sajadsoft\BiometricDevices\Services\CommandHandlers\GetUserInfoHandler;
use Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper;
use Sajadsoft\BiometricDevices\Tests\TestCase;

class GetUserInfoHandlerTest extends TestCase
{
    public function test_device_response_does_not_receive_an_extra_ack(): void
    {
        config()->set('biometric-devices.models.device', NonExistingDeviceModel::class);

        $handler = new GetUserInfoHandler(new AIFaceWebSocketMapper);

        $response = $handler->handle([
            'ret' => 'getuserinfo',
            'result' => true,
            'sn' => 'SERIAL-1',
            'enrollid' => 10,
            'backupnum' => 0,
            'record' => str_repeat('A', 2048),
        ], null);

        self::assertNull($response);
    }
}
