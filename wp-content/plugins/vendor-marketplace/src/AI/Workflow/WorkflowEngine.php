<?php
namespace Vendor\AI\Workflow;

use Vendor\AI\Workflow\Steps\WorkflowStepInterface;
use Vendor\AI\Workflow\AIJobContext;
use Vendor\AI\Events\EventBusInterface;

/**
 * Simple WorkflowEngine wrapper to instrument steps centrally.
 */
class WorkflowEngine
{
    /** @var WorkflowStepInterface[] */
    private array $steps;
    private EventBusInterface $events;

    public function __construct(array $steps, EventBusInterface $events)
    {
        $this->steps = $steps;
        $this->events = $events;
    }

    public function run(AIJobContext $ctx): void
    {
        foreach ($this->steps as $step) {
            $stepName = get_class($step);
            $this->events->dispatch('step.started', ['job_id' => $ctx->jobId ?? null, 'step' => $stepName, 'ts' => microtime(true) * 1000]);
            $start = microtime(true);
            try {
                if ($ctx->isCancelled()) {
                    throw new \Vendor\AI\Exceptions\JobCancelledException('Job cancelled');
                }
                if ($ctx->deadlineExceeded()) {
                    throw new \Vendor\AI\Exceptions\DeadlineExceededException('Deadline exceeded');
                }

                $step->execute($ctx);

                $latency = intval((microtime(true) - $start) * 1000);
                $this->events->dispatch('step.completed', ['job_id' => $ctx->jobId ?? null, 'step' => $stepName, 'latency_ms' => $latency]);
            } catch (\Throwable $e) {
                $latency = intval((microtime(true) - $start) * 1000);
                $this->events->dispatch('step.failed', ['job_id' => $ctx->jobId ?? null, 'step' => $stepName, 'error' => $e->getMessage(), 'latency_ms' => $latency]);
                throw $e; // let worker handle marking failed/retry
            }
        }
    }
}
