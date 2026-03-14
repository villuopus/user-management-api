<?php

namespace App\Services;

use App\Contracts\EventListenerInterface;
use App\Models\User;

class EventDispatcher
{
    /** @var array<string, EventListenerInterface[]> */
    private array $listeners = [];

    /** @var EventDispatcher|null */
    private static ?EventDispatcher $instance = null;

    /** @var array<array{event: string, payload: array, timestamp: int}> */
    private array $eventLog = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a listener
     *
     * @param EventListenerInterface $listener
     * @return void
     */
    public function register(EventListenerInterface $listener): void
    {
        $events = $listener->subscribesTo();

        foreach ($events as $event) {
            $this->listeners[$event][] = $listener;
        }
    }

    /**
     * Dispatch an event to all registered listeners
     *
     * @param string $eventName
     * @param array $payload
     * @return void
     */
    public function dispatch(string $eventName, array $payload = []): void
    {
        $this->eventLog[] = [
            'event' => $eventName,
            'payload' => $payload,
            'timestamp' => time()
        ];

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            // CROSS-FILE ISSUE: calling handle() with 3 args,
            // but EventListenerInterface::handle() only takes 2
            $listener->handle($eventName, $payload, time());
        }
    }

    /**
     * Dispatch and collect results from listeners
     * 
     * @param string $eventName
     * @param array $payload
     * @return array  Results from each listener
     */
    public function dispatchAndCollect(string $eventName, array $payload = []): array
    {
        $results = [];

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            // CROSS-FILE: handle() returns void per interface, but we assign its return value
            $results[] = $listener->handle($eventName, $payload);
        }

        return $results;
    }
}
