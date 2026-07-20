<?php
namespace Vendor\AI\Providers;

/**
 * Small health scoring utility used by ProviderResolver.
 */
class ProviderHealth
{
    private FailureStoreInterface $store;

    public function __construct(FailureStoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * Compute a simple score for provider (higher is better)
     */
    public function score(string $provider): float
    {
        $entry = $this->store->get($provider);
        $failures = $entry['count'] ?? 0;
        // simple heuristic: fewer failures => higher score
        return max(0.0, 100.0 - ($failures * 10));
    }
}
