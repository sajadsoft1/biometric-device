<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests\Unit;

use LogicException;
use Sajadsoft\BiometricDevices\Contracts\CommandHandlerInterface;
use Sajadsoft\BiometricDevices\DTOs\Responses\QrCodeDTO;
use Sajadsoft\BiometricDevices\Enums\DeviceCommand;
use Sajadsoft\BiometricDevices\Events\QrCodeReceived;
use Sajadsoft\BiometricDevices\Services\CommandHandlers\SendQrCodeHandler;
use Sajadsoft\BiometricDevices\Services\DataMappers\AIFaceWebSocketMapper;
use Sajadsoft\BiometricDevices\Services\Pipelines\RouteToHandlerPipe;
use Sajadsoft\BiometricDevices\Tests\TestCase;

class QrCodeHandlerTest extends TestCase
{
    public function test_it_dispatches_a_qr_event_and_returns_the_default_denied_response(): void
    {
        $received = null;
        $this->app['events']->listen(QrCodeReceived::class, function (QrCodeReceived $event) use (&$received): void {
            $received = $event->qrCode;
        });

        $response = (new SendQrCodeHandler(new AIFaceWebSocketMapper))->handle([
            'cmd' => 'sendqrcode',
            'sn' => 'DEVICE-1',
            'record' => 'member:42',
            'enrollid' => '42',
            'time' => '2026-09-15 08:30:00',
        ], null);

        self::assertInstanceOf(QrCodeDTO::class, $received);
        self::assertSame('member:42', $received->qrCodeData);
        self::assertSame('DEVICE-1', $received->deviceSerial);
        self::assertSame('2026-09-15 08:30:00', $received->timestamp->format('Y-m-d H:i:s'));
        self::assertSame(0, $response['access']);
        self::assertSame(42, $response['enrollid']);
        self::assertTrue($response['result']);
    }

    public function test_it_rejects_a_qr_message_without_content(): void
    {
        $response = (new SendQrCodeHandler(new AIFaceWebSocketMapper))->handle([
            'cmd' => 'sendqrcode',
            'sn' => 'DEVICE-1',
        ], null);

        self::assertFalse($response['result']);
        self::assertSame('sendqrcode', $response['ret']);
    }

    public function test_an_application_can_replace_the_qr_handler_from_configuration(): void
    {
        config()->set('biometric-devices.handlers.sendqrcode', AllowQrCodeHandler::class);

        $context = (new RouteToHandlerPipe)->handle([
            'command' => DeviceCommand::SEND_QR_CODE,
        ], static fn (array $context): array => $context);

        self::assertInstanceOf(AllowQrCodeHandler::class, $context['handler']);
        self::assertSame(['ret' => 'sendqrcode', 'result' => true, 'access' => 1], $context['handler']->handle([], null));
    }

    public function test_an_application_can_replace_any_known_operational_handler(): void
    {
        config()->set('biometric-devices.handlers.sendlog', CustomOperationalHandler::class);

        $context = (new RouteToHandlerPipe)->handle([
            'command' => DeviceCommand::SEND_LOG,
        ], static fn (array $context): array => $context);

        self::assertInstanceOf(CustomOperationalHandler::class, $context['handler']);
        self::assertSame('sendlog', $context['handler']->getCommandName());
    }

    public function test_a_custom_handler_must_implement_the_package_contract(): void
    {
        config()->set('biometric-devices.handlers.sendlog', \stdClass::class);
        $this->expectException(LogicException::class);

        (new RouteToHandlerPipe)->handle([
            'command' => DeviceCommand::SEND_LOG,
        ], static fn (array $context): array => $context);
    }
}

class AllowQrCodeHandler extends SendQrCodeHandler
{
    public function __construct()
    {
        parent::__construct(new AIFaceWebSocketMapper);
    }

    public function handle(array $data, $connection): ?array
    {
        return ['ret' => 'sendqrcode', 'result' => true, 'access' => 1];
    }
}

class CustomOperationalHandler implements CommandHandlerInterface
{
    public function handle(array $data, $connection): ?array
    {
        return null;
    }

    public function getCommandName(): string
    {
        return 'sendlog';
    }
}
