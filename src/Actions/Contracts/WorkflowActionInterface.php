<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Contracts;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;

interface WorkflowActionInterface
{
    public static function key(): string;

    public function handle(ActionContext $context): ActionResult;
}
