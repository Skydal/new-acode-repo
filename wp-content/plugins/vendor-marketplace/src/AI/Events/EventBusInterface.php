<?php
namespace Vendor\AI\Events;

interface EventBusInterface
{
    public function dispatch(string $eventName, array $payload = []): void;
}
