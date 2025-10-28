<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;
use Sajadsoft\BiometricDevices\Enums\DeviceCommand;
use ValueError;

/**
 * Identify command type from message
 */
class IdentifyCommandPipe
{
    public function handle(array $context, Closure $next)
    {
        $data = $context['normalized_data'];

        // تشخیص نوع command
        $commandValue = $data['cmd'] ?? $data['ret'] ?? null;

        try {
            $context['command'] = DeviceCommand::from($commandValue);
            $context['command_name'] = $commandValue;
        } catch (ValueError $e) {
            Logger::debug('Unknown command type', [
                'command' => $commandValue,
                'data' => $data,
            ]);
            $context['command'] = null;
            $context['command_name'] = $commandValue;
        }

        return $next($context);
    }
}
