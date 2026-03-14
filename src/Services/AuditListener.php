<?php

namespace App\Services;

use App\Contracts\EventListenerInterface;
use App\Models\User;

class AuditListener implements EventListenerInterface
{
    private Logger $logger;

    public function __construct()
    {
        // CROSS-FILE: Logger constructor accepts optional $logFile string param,
        // but we're passing an int
        $this->logger = new Logger(42);
    }

    /**
     * Handle the event
     *
     * INTERFACE VIOLATION: interface says handle(string, array): void
     * This version takes only 1 param, returns string
     *
     * @param string $eventName
     * @return string
     */
    public function handle(string $eventName): string
    {
        $entry = date('Y-m-d H:i:s') . " | Event: {$eventName}";

        // CROSS-FILE: Logger::info() takes (string, array), passing (string, string)
        $this->logger->info($entry, "audit_context");

        // CROSS-FILE: calling method that doesn't exist on Logger
        $this->logger->flush();

        return $entry;  // interface says void return
    }

    /**
     * INTERFACE VIOLATION: subscribesTo() should return array<string>
     * Returns array with mixed types instead
     *
     * @return array
     */
    public function subscribesTo(): array
    {
        return ['user.created', 'user.updated', 'user.deleted', 404, null];
    }
}
