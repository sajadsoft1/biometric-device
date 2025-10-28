<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * Log message for debugging
 */
class LogMessagePipe
{
    public function handle(array $context, Closure $next)
    {
        Logger::debug('Message processed through pipeline', [
            'command'     => $context['command_name'] ?? 'unknown',
            'is_request'  => $context['is_request'] ?? false,
            'is_response' => $context['is_response'] ?? false,
            'has_handler' => isset($context['handler']),
        ]);

        return $next($context);
    }
}
