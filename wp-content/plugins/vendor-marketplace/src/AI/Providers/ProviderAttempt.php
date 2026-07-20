<?php
namespace Vendor\AI\Providers;

class ProviderAttempt
{
    public string $provider;
    public bool $success;
    public ?int $latencyMs = null;
    public ?string $error = null;

    public function __construct(string $provider, bool $success, ?int $latencyMs = null, ?string $error = null)
    {
        $this->provider = $provider;
        $this->success = $success;
        $this->latencyMs = $latencyMs;
        $this->error = $error;
    }
}
