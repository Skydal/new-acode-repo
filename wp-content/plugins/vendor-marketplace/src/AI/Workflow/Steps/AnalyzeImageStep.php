<?php
namespace Vendor\AI\Workflow\Steps;

use Vendor\AI\Providers\ProviderFailover;
use Vendor\AI\Events\EventBusInterface;
use Vendor\AI\DTO\ImageAnalysisResult;
use Vendor\AI\Workflow\AIJobContext;
use Psr\Log\LoggerInterface;

/**
 * Simplified AnalyzeImageStep that delegates all provider selection/retry/failover
 * to ProviderFailover. Uses ImageLoader and VisionCache provided by the app.
 */
class AnalyzeImageStep implements WorkflowStepInterface
{
    private $imageLoader; // ImageLoaderInterface (assumed)
    private $visionCache; // VisionCacheInterface (assumed)
    private ProviderFailover $failover;
    private EventBusInterface $events;
    private LoggerInterface $logger;
    private int $perImageTimeoutSec;

    public function __construct($imageLoader, $visionCache, ProviderFailover $failover, EventBusInterface $events, LoggerInterface $logger, int $perImageTimeoutSec = 30)
    {
        $this->imageLoader = $imageLoader;
        $this->visionCache = $visionCache;
        $this->failover = $failover;
        $this->events = $events;
        $this->logger = $logger;
        $this->perImageTimeoutSec = $perImageTimeoutSec;
    }

    public function execute(AIJobContext $ctx): void
    {
        $images = $ctx->images;
        $total = count($images);
        if ($total === 0) {
            $ctx->updateProgress(30);
            return;
        }

        foreach ($images as $i => $imageRef) {
            // prepare (ImageLoader should return local path)
            $localPath = $this->imageLoader->load($imageRef);

            // Build cache key via visionCache (assumed interface)
            $cacheKey = $this->visionCache->makeKey($localPath, $ctx->metadata);

            $resultDto = $this->visionCache->remember($cacheKey, 24 * 3600, function() use ($localPath, $ctx) {
                // delegate to failover — step no longer handles retries or circuits
                $result = $this->failover->execute('vision', function($provider) use ($localPath, $ctx) {
                    return $provider->analyzeImage($localPath, [
                        'timeout' => $this->perImageTimeoutSec,
                        'locale' => $ctx->metadata['locale'] ?? 'en',
                        'job_id' => $ctx->jobId,
                    ]);
                }, $ctx);

                // result is an ImageAnalysisResult
                if ($result instanceof ImageAnalysisResult) {
                    return $result->toArray();
                }

                // normalize
                return is_array($result) ? $result : (array)$result;
            });

            // add to metadata analysis array
            $ctx->metadata['analysis'][$i] = $resultDto;

            // update progress (distribute between 10 and 30 for this step)
            $progress = 10 + intval(20 * ($i + 1) / max(1, $total));
            $ctx->updateProgress($progress);
        }

        $ctx->updateProgress(30);
    }
}
