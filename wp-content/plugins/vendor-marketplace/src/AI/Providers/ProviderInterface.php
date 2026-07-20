<?php
namespace Vendor\AI\Providers;

interface ProviderInterface
{
    /**
     * Identifier for the provider (e.g., 'gemini', 'openai')
     * @return string
     */
    public function name(): string;

    /**
     * Capability-aware analyze method for images.
     * Should enforce its own timeout internally (client-level).
     *
     * @param string $localFilePath
     * @param array $opts
     * @return array|object normalized raw result (provider-specific)
     * @throws \Vendor\AI\Exceptions\ProviderException on transient/provider-specific failures
     */
    public function analyzeImage(string $localFilePath, array $opts);
}
