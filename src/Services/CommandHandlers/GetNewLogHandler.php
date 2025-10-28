<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Services\CommandHandlers;

/**
 * Handler for getnewlog - same as GetAllLogHandler
 */
class GetNewLogHandler extends GetAllLogHandler
{
    public function getCommandName(): string
    {
        return 'getnewlog';
    }
}
