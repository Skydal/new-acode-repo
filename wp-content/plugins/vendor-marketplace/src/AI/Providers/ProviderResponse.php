<?php
namespace Vendor\AI\Providers;

class ProviderResponse
{
    public string $provider;
    public array $payload;
    public ?float $confidence = null;
    public ?float $costUsd = null;
    public ?int $latencyMs = null;
    public ?array $tokens = null;

    public function __construct(string $provider, array $payload)
    {
        $this->provider = $provider;
        $this->payload = $payload;
        $this->confidence = $payload['confidence'] ?? null;
        $this->costUsd = $payload['cost_usd'] ?? null;
        $this->tokens = $payload['tokens'] ?? null;
    }
}
