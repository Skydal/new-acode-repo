<?php
namespace Vendor\AI\Workflow\Steps;

interface WorkflowStepInterface
{
    public function execute(\Vendor\AI\Workflow\AIJobContext $ctx): void;
}
