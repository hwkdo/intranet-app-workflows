<?php

namespace Hwkdo\IntranetAppWorkflows\Commands;

use Illuminate\Console\Command;

class IntranetAppWorkflowsCommand extends Command
{
    public $signature = 'intranet-app-workflows';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
