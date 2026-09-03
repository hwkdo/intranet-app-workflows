<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Enums;

enum HistoryWhat: string
{
    case Initiated = 'initiated';
    case Submitted = 'submitted';
    case Claimed = 'claimed';
    case Released = 'released';
    case Forwarded = 'forwarded';
    case Cancelled = 'cancelled';
}
