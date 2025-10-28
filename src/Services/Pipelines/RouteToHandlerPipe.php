<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;

/**
 * Route message to appropriate handler
 */
class RouteToHandlerPipe
{
    public function handle(array $context, Closure $next)
    {
        $command = $context['command'];

        if ($command) {
            // دریافت handler class
            $handlerClass       = $command->getHandlerClass();
            $context['handler'] = app($handlerClass);
        } else {
            $context['handler'] = null;
        }

        return $next($context);
    }
}
