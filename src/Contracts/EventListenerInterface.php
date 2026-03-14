<?php

namespace App\Contracts;

interface EventListenerInterface
{
    /**
     * Handle the event
     *
     * @param string $eventName
     * @param array $payload
     * @return void
     */
    public function handle(string $eventName, array $payload): void;

    /**
     * Get the list of events this listener subscribes to
     *
     * @return array<string>
     */
    public function subscribesTo(): array;
}
