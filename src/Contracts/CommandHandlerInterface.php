<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Contracts;

/**
 * Interface for command handlers
 */
interface CommandHandlerInterface
{
    /**
     * Handle incoming data from device
     *
     * @param  array  $data  The decoded message data
     * @param  mixed  $connection  The connection resource (socket, etc.)
     * @return array|null Response to send back to device
     */
    public function handle(array $data, $connection): ?array;

    /** Get the command name this handler processes */
    public function getCommandName(): string;
}
