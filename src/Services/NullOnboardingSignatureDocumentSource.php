<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Services;

use Hwkdo\IntranetAppWorkflows\Contracts\OnboardingSignatureDocumentSource;
use Illuminate\Database\Eloquent\Model;

final class NullOnboardingSignatureDocumentSource implements OnboardingSignatureDocumentSource
{
    public function linksFor(Model $user): array
    {
        return [];
    }
}
