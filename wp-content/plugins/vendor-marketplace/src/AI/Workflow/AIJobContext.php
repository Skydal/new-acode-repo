<?php
namespace Vendor\AI\Workflow;

class AIJobContext
{
    public int $jobId;
    public array $images = [];
    public array $metadata = [];
    private $progressCallback;
    private int $deadlineMs = 0;

    public function __construct(int $jobId, array $images, callable $progressCallback, int $deadlineMs = 0)
    {
        $this->jobId = $jobId;
        $this->images = $images;
        $this->progressCallback = $progressCallback;
        $this->deadlineMs = $deadlineMs;
    }

    public function updateProgress(int $percent): void
    {
        ($this->progressCallback)($percent);
    }

    public function isCancelled(): bool
    {
        return (bool)($this->metadata['cancelled'] ?? false);
    }

    public function deadlineExceeded(): bool
    {
        if ($this->deadlineMs === 0) {
            return false;
        }
        return microtime(true) * 1000 > $this->deadlineMs;
    }
}
