<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;

/**
 * Log message for debugging
 */
class LogMessagePipe
{
    public function handle(array $context, Closure $next)
    {
        return $next($context);
    }
}
