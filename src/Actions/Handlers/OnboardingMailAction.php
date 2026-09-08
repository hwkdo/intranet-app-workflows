<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Mail\OnboardingDocumentsMail;
use Hwkdo\IntranetAppWorkflows\Support\OnboardingLinkBuilder;
use Hwkdo\IntranetAppWorkflows\Support\PhaseDGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class OnboardingMailAction implements WorkflowActionInterface
{
    public static function key(): string
    {
        return 'ma_neu.onboarding_mail';
    }

    public function handle(ActionContext $context): ActionResult
    {
        if ($early = PhaseDGuard::preflight()) {
            return $early;
        }

        $username = trim((string) $context->payloadValue('username', ''));
        if ($username === '') {
            return ActionResult::failed('Username fehlt im Payload', retryable: false);
        }

        $user = WorkflowModels::userQuery()->where('username', $username)->first();
        if (! $user) {
            return ActionResult::failed("Kein User mit username [{$username}] gefunden.");
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return ActionResult::failed("User [{$username}] hat keine E-Mail-Adresse.", retryable: false);
        }

        try {
            $link = OnboardingLinkBuilder::forUser($user);
        } catch (Throwable $e) {
            return ActionResult::failed($e->getMessage(), retryable: false);
        }

        if ($dry = PhaseDGuard::assertNotDryRunOrMessage(
            actionLabel: "Onboarding-Mail an {$email}",
            output: ['onboarding_email' => $email, 'onboarding_link' => $link],
            messages: ["Onboarding-Link: {$link}"],
        )) {
            return $dry;
        }

        try {
            Mail::to($email)->send(new OnboardingDocumentsMail($link));
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Onboarding-Mail fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Onboardingmail gesendet zu {$email}",
            output: [
                'onboarding_email' => $email,
                'onboarding_link' => $link,
            ],
        );
    }
}
