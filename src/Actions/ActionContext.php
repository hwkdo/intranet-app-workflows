<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionAttempt;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionRun;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStepAction;

final class ActionContext
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly WorkflowFlow $flow,
        public readonly WorkflowStepAction $stepAction,
        public readonly WorkflowActionRun $actionRun,
        public readonly WorkflowActionAttempt $attempt,
        public readonly array $payload,
        public readonly array $config,
    ) {}

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }
}
