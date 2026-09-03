<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Enums;

enum ActionRunStatus: string
{
    case Pending = 'pending';
    case Waiting = 'waiting';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Partial = 'partial';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Failed,
            self::Skipped,
            self::Partial,
        ], true);
    }

    /** Terminal status that still allows the pipeline to continue (mark-only policy). */
    public function allowsPipelineContinue(): bool
    {
        return $this->isTerminal();
    }
}
