<?php
namespace Vendor\AI\Providers;

interface FailureStoreInterface
{
    public function recordFailure(string $provider, int $nowMillis, int $windowMs): void;
    public function reset(string $provider): void;
    public function get(string $provider): array;
    public function setOpenUntil(string $provider, int $ms): void;
}
