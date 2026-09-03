<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;

final class NoOpAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'demo.noop';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $label = (string) ($context->config['label'] ?? 'No-op');

        return ActionResult::succeeded($label.' executed');
    }
}
