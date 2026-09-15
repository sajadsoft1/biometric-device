<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sajadsoft\BiometricDevices\Support\Logger;

class LoggerTest extends TestCase
{
    public function test_large_biometric_payloads_are_replaced_with_metadata(): void
    {
        $payload = str_repeat('A', 50000);

        $sanitized = Logger::sanitize([
            'sn' => 'SERIAL-1',
            'record' => $payload,
            'nested' => ['image' => $payload],
            'batch' => ['record' => [['enrollid' => 1], ['enrollid' => 2]]],
        ]);

        self::assertSame('SERIAL-1', $sanitized['sn']);
        self::assertTrue($sanitized['record']['redacted']);
        self::assertSame(50000, $sanitized['record']['length']);
        self::assertSame(hash('sha256', $payload), $sanitized['nested']['image']['sha256']);
        self::assertSame(2, $sanitized['batch']['record']['count']);
    }
}
