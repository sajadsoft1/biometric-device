<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

/**
 * Pipeline for processing device messages
 */
class MessagePipeline
{
    protected array $pipes = [
        ValidateMessagePipe::class,
        DecodeMessagePipe::class,
        IdentifyCommandPipe::class,
        RouteToHandlerPipe::class,
        LogMessagePipe::class,
    ];

    /** Process message through pipeline */
    public function process(array $context, callable $finalHandler)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function ($next, $pipe) {
                return function ($context) use ($next, $pipe) {
                    return app($pipe)->handle($context, $next);
                };
            },
            $finalHandler
        );

        return $pipeline($context);
    }
}
