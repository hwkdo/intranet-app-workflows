<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Enums;

enum FlowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Active;
    }
}
