<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    public function __construct(
        #[Description('Standard-Seitengröße für Workflow-Listen')]
        public int $maxItemsPerPage = 25,
    ) {}
}
