<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;
use Sajadsoft\BiometricDevices\Support\Logger;

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
            $handlerClass = $command->getHandlerClass();
            $context['handler'] = app($handlerClass);

            Logger::debug('Message routed to handler', [
                'command' => $command->value,
                'handler' => $handlerClass,
            ]);
        } else {
            $context['handler'] = null;
        }

        return $next($context);
    }
}
