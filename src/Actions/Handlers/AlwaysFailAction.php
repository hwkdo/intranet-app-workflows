<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;

final class AlwaysFailAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'demo.always_fail';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $message = (string) ($context->config['message'] ?? 'Demo-Fehler');

        return ActionResult::failed($message, retryable: true);
    }
}
