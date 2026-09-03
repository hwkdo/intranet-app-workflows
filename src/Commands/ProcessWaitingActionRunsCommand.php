<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Commands;

use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Illuminate\Console\Command;

class ProcessWaitingActionRunsCommand extends Command
{
    protected $signature = 'workflows:process-waiting-actions';

    protected $description = 'Führt fällige wartende Workflow-ActionRuns aus (Stichtag erreicht)';

    public function handle(WorkflowOrchestrator $orchestrator): int
    {
        $count = $orchestrator->processWaitingRuns();
        $this->info("Processed {$count} waiting action run(s).");

        return self::SUCCESS;
    }
}
