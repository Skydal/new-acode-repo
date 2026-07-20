<?php
namespace Vendor\AI\Providers;

/**
 * Minimal in-memory failure store. Replace with Redis/DB-backed implementation for production.
 */
class ProviderFailureStore
{
    // providerName => ['count'=>int,'last_failed_at'=>ms,'opened_until'=>ms]
    private array $failures = [];

    public function recordFailure(string $provider, int $nowMillis, int $windowMs): void
    {
        $entry = $this->failures[$provider] ?? ['count' => 0, 'last_failed_at' => 0, 'opened_until' => 0];
        // reset count if last failure is outside window
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
        return $this->failures[$provider] ?? ['count'=>0,'last_failed_at'=>0,'opened_until'=>0];
    }

    public function setOpenUntil(string $provider, int $ms): void
    {
        $this->failures[$provider]['opened_until'] = $ms;
    }
}
