<?php
namespace Vendor\AI\Providers;

/**
 * Simple in-memory failure store implementing FailureStoreInterface.
 * Replace with Redis/DB-backed implementation in production.
 */
class MemoryFailureStore implements FailureStoreInterface
{
    private array $failures = [];

    public function recordFailure(string $provider, int $nowMillis, int $windowMs): void
    {
        $entry = $this->failures[$provider] ?? ['count' => 0, 'last_failed_at' => 0, 'opened_until' => 0];
        if (($entry['last_failed_at'] ?? 0) + $windowMs < $nowMillis) {
            $entry['count'] = 0;
        }
        $entry['count'] = ($entry['count'] ?? 0) + 1;
        $entry['last_failed_at'] = $nowMillis;
        $this->failures[$provider] = $entry;
    }

    public function reset(string $provider): void
    {
        unset($this->failures[$provider]);
    }

    public function get(string $provider): array
    {
        return $this->failures[$provider] ?? ['count' => 0, 'last_failed_at' => 0, 'opened_until' => 0];
    }

    public function setOpenUntil(string $provider, int $ms): void
    {
        $this->failures[$provider]['opened_until'] = $ms;
    }
}
