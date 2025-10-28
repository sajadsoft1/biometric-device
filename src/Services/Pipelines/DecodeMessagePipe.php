<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\Pipelines;

use Closure;

/**
 * Decode and normalize message data
 */
class DecodeMessagePipe
{
    public function handle(array $context, Closure $next)
    {
        $data = $context['data'];

        // Normalize data
        $context['normalized_data'] = $this->normalize($data);
        $context['is_request'] = isset($data['cmd']);
        $context['is_response'] = isset($data['ret']);

        return $next($context);
    }

    protected function normalize(array $data): array
    {
        // تبدیل admin از string به integer
        if (isset($data['admin']) && is_string($data['admin'])) {
            $data['admin'] = (int) $data['admin'];
        }

        // نرمال‌سازی backupnum
        if (isset($data['backupnum']) && is_string($data['backupnum'])) {
            $data['backupnum'] = (int) $data['backupnum'];
        }

        // نرمال‌سازی آرایه record
        if (isset($data['record']) && is_array($data['record'])) {
            $data['record'] = array_map([$this, 'normalize'], $data['record']);
        }

        return $data;
    }
}
