<?php
namespace Vendor\AI\Providers;

use Vendor\AI\Events\EventBusInterface;
use Vendor\AI\DTO\ImageAnalysisResult;
use Vendor\AI\Exceptions\ProviderException;
use Vendor\AI\Exceptions\AllProvidersFailedException;
use Vendor\AI\Workflow\AIJobContext;
use Psr\Log\LoggerInterface;

class ProviderFailover
{
    private ProviderResolver $resolver;
    private ProviderFailureStore $store;
    private EventBusInterface $events;
    private LoggerInterface $logger;

    // configuration
    private int $maxAttemptsPerProvider;
    private int $baseBackoffMs;
    private int $circuitThreshold; // failures to open circuit
    private int $circuitOpenMs; // how long to keep circuit open
    private int $failureWindowMs; // counting window for failures

    public function __construct(
        ProviderResolver $resolver,
        ProviderFailureStore $store,
        EventBusInterface $events,
        LoggerInterface $logger,
        int $maxAttemptsPerProvider = 2,
        int $baseBackoffMs = 500,
        int $circuitThreshold = 5,
        int $circuitOpenMs = 300_000, // 5 minutes
        int $failureWindowMs = 60_000 // 1 minute
    ) {
        $this->resolver = $resolver;
        $this->store = $store;
        $this->events = $events;
        $this->logger = $logger;
        $this->maxAttemptsPerProvider = $maxAttemptsPerProvider;
        $this->baseBackoffMs = $baseBackoffMs;
        $this->circuitThreshold = $circuitThreshold;
        $this->circuitOpenMs = $circuitOpenMs;
        $this->failureWindowMs = $failureWindowMs;
    }

    /**
     * Execute the given callable with automatic failover.
     *
     * @param string $capability
     * @param callable $callProvider function(ProviderInterface $provider): array|object
     * @param AIJobContext $ctx
     * @return ImageAnalysisResult
     * @throws AllProvidersFailedException
     */
    public function execute(string $capability, callable $callProvider, AIJobContext $ctx): ImageAnalysisResult
    {
        $now = $this->nowMillis();
        $candidates = $this->resolver->healthyCandidates($capability);

        if (empty($candidates)) {
            throw new AllProvidersFailedException("No candidates for capability {$capability}");
        }

        $lastException = null;

        foreach ($candidates as $providerName => $provider) {
            // Check circuit
            $entry = $this->store->get($providerName);
            $openedUntil = $entry['opened_until'] ?? 0;
            if ($openedUntil > $now) {
                $this->events->dispatch('provider.skipped', ['provider' => $providerName, 'reason' => 'circuit_open']);
                continue;
            }

            // Attempt this provider up to N times
            for ($attempt = 1; $attempt <= $this->maxAttemptsPerProvider; $attempt++) {
                if ($ctx->isCancelled()) {
                    throw new \RuntimeException('Job cancelled');
                }
                if ($ctx->deadlineExceeded()) {
                    throw new \RuntimeException('Job deadline exceeded');
                }

                $attemptStart = $this->nowMillis();
                $this->events->dispatch('provider.attempt', [
                    'provider' => $providerName, 'attempt' => $attempt,
                    'job_id' => $ctx->jobId ?? null
                ]);

                try {
                    $raw = $callProvider($provider);
                    $latency = intval($this->nowMillis() - $attemptStart);

                    // Normalize result into DTO (provider may return array/object)
                    $dto = new ImageAnalysisResult($providerName, is_array($raw) ? $raw : (array)$raw);
                    $dto->latencyMs = $latency;
                    $dto->confidence = $raw['confidence'] ?? ($raw->confidence ?? null);
                    $dto->costUsd = $raw['cost_usd'] ?? ($raw->cost_usd ?? null);
                    $dto->tokens = $raw['tokens'] ?? null;

                    // success => reset failure count
                    $this->store->reset($providerName);
                    $this->events->dispatch('provider.attempt.completed', [
                        'provider' => $providerName, 'latency_ms' => $latency, 'job_id' => $ctx->jobId ?? null
                    ]);

                    // record history for job
                    $this->events->dispatch('provider.used', ['provider' => $providerName, 'latency_ms' => $latency, 'job_id' => $ctx->jobId ?? null]);

                    // Return the DTO
                    return $dto;
                } catch (ProviderException $pe) {
                    $latency = intval($this->nowMillis() - $attemptStart);
                    $this->logger->warning("Provider {$providerName} attempt {$attempt} failed: " . $pe->getMessage());
                    $this->events->dispatch('provider.attempt.failed', [
                        'provider' => $providerName,
                        'attempt' => $attempt,
                        'error' => $pe->getMessage(),
                        'latency_ms' => $latency,
                        'job_id' => $ctx->jobId ?? null
                    ]);

                    $lastException = $pe;
                    // record failure
                    $this->store->recordFailure($providerName, $this->nowMillis(), $this->failureWindowMs);

                    // check threshold to open circuit
                    $entry = $this->store->get($providerName);
                    if (($entry['count'] ?? 0) >= $this->circuitThreshold) {
                        $openUntil = $this->nowMillis() + $this->circuitOpenMs;
                        $this->store->setOpenUntil($providerName, $openUntil);
                        $this->events->dispatch('provider.circuit.open', ['provider' => $providerName, 'open_until' => $openUntil]);
                        break; // break attempts for this provider, move to next provider
                    }

                    // backoff before retrying
                    $backoff = $this->baseBackoffMs * (2 ** ($attempt - 1));
                    usleep(min(5_000, $backoff) * 1000); // cap backoff to 5s
                    continue;
                } catch (\Throwable $t) {
                    $latency = intval($this->nowMillis() - $attemptStart);
                    $this->logger->error("Provider {$providerName} unexpected error: " . $t->getMessage());
                    $lastException = $t;
                    // treat as failure
                    $this->store->recordFailure($providerName, $this->nowMillis(), $this->failureWindowMs);
                    break;
                }
            } // attempts loop

            // provider failed after attempts
            $this->events->dispatch('provider.failed', ['provider' => $providerName, 'job_id' => $ctx->jobId ?? null]);
        } // candidates loop

        // If reached here, all providers failed
        $this->events->dispatch('provider.all_failed', ['job_id' => $ctx->jobId ?? null]);
        throw new AllProvidersFailedException('All providers failed', 0, $lastException);
    }

    private function nowMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }
}
