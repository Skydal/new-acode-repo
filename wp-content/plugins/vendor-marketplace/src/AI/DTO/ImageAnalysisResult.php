<?php
namespace Vendor\AI\DTO;

class ImageAnalysisResult
{
    public string $provider;
    public array $raw; // raw provider payload (optionally large — save only IDs in DB)
    public ?float $confidence = null;
    public ?float $costUsd = null;
    public ?int $latencyMs = null;
    public ?array $tokens = null;

    public function __construct(string $provider, array $raw = [])
    {
        $this->provider = $provider;
        $this->raw = $raw;
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'confidence' => $this->confidence,
            'cost_usd' => $this->costUsd,
            'latency_ms' => $this->latencyMs,
            'tokens' => $this->tokens,
            'raw' => $this->raw,
        ];
    }
}
