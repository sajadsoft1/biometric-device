<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;

/**
 * Validate message structure
 */
class ValidateMessagePipe
{
    public function handle(array $context, Closure $next)
    {
        $data = $context['data'] ?? null;

        // بررسی داده خالی نباشد
        if (empty($data)) {
            Logger::debug('Empty message received', $context);

            return;
        }

        // بررسی cmd یا ret وجود داشته باشد
        if ( ! isset($data['cmd']) && ! isset($data['ret'])) {
            Logger::debug('Message without cmd or ret field', $context);

            return;
        }

        // ادامه به pipe بعدی
        return $next($context);
    }
}
