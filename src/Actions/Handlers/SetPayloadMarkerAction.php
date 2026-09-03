<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;

final class SetPayloadMarkerAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'demo.set_marker';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $key = (string) ($context->config['marker_key'] ?? 'marker');
        $value = $context->config['marker_value'] ?? true;

        return ActionResult::succeeded(
            message: "Marker [{$key}] gesetzt",
            output: [$key => $value],
        );
    }
}
