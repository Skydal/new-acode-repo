<?php
namespace Vendor\AI\Providers;

use Vendor\AI\Providers\ProviderInterface;
use Vendor\AI\Providers\ProviderHealth;

/**
 * Lightweight resolver that orders providers by health score.
 * In production, wire this to your service container/config to provide provider instances.
 */
class ProviderResolver
{
    /** @var ProviderInterface[] */
    private array $providers;
    private ProviderHealth $health;

    public function __construct(array $providers, ProviderHealth $health)
    {
        $this->providers = $providers; // associative array name => instance
        $this->health = $health;
    }

    /**
     * Return associative array of providerName => ProviderInterface ordered by health score desc
     * @param string $capability
     * @return ProviderInterface[]
     */
    public function healthyCandidates(string $capability): array
    {
        // Filter providers by capability if providers expose that (left as simple pass-through)
        $list = $this->providers;

        // compute scores
        $scores = [];
        foreach ($list as $name => $provider) {
            $scores[$name] = $this->health->score($name);
        }

        // sort by score desc
        arsort($scores);

        $ordered = [];
        foreach ($scores as $name => $_s) {
            if (isset($list[$name])) {
                $ordered[$name] = $list[$name];
            }
        }

        return $ordered;
    }
}
