<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Contracts;

use Illuminate\Database\Eloquent\Model;

interface OnboardingSignatureDocumentSource
{
    /**
     * @return list<array{title: string, url: string}>
     */
    public function linksFor(Model $user): array;
}
