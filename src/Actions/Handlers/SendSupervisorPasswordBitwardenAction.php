<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions\Handlers;

use Hwkdo\IntranetAppWorkflows\Actions\ActionContext;
use Hwkdo\IntranetAppWorkflows\Actions\ActionResult;
use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenSendGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Mail\SupervisorPasswordSendMail;
use Hwkdo\IntranetAppWorkflows\Support\GvpSupervisorResolver;
use Hwkdo\IntranetAppWorkflows\Support\PhaseBGuard;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendSupervisorPasswordBitwardenAction implements WorkflowActionInterface
{
    public function __construct(
        private readonly BitwardenSendGatewayInterface $bitwarden,
    ) {}

    public static function key(): string
    {
        return 'ma_neu.send_supervisor_password_bitwarden';
    }

    public function handle(ActionContext $context): ActionResult
    {
        $username = trim((string) $context->payloadValue('username', ''));
        if ($early = PhaseBGuard::preflight($username !== '' ? $username : null)) {
            return $early;
        }

        $password = (string) $context->payloadValue('password', '');
        if ($password === '') {
            return ActionResult::failed('Kein Passwort im Payload – Activate-Schritt muss zuerst erfolgreich laufen.', retryable: true);
        }

        $supervisorId = GvpSupervisorResolver::userIdForAbteilung($context->payloadValue('abteilung'));
        if ($supervisorId === null) {
            return ActionResult::failed('Kein Vorgesetzter zur Abteilung ermittelbar.', retryable: false);
        }

        $supervisor = WorkflowModels::userQuery()->find($supervisorId);
        $email = trim((string) ($supervisor?->email ?? ''));
        if ($email === '') {
            return ActionResult::failed("Vorgesetzter #{$supervisorId} hat keine E-Mail-Adresse.", retryable: false);
        }

        $vorname = trim((string) $context->payloadValue('vorname', ''));
        $nachname = trim((string) $context->payloadValue('nachname', ''));
        $employeeName = trim("{$vorname} {$nachname}");
        if ($employeeName === '') {
            $employeeName = $username;
        }

        $sendName = 'AD-Passwort: '.$employeeName.' ('.$username.')';
        $maxAccess = max(1, (int) config('intranet-app-workflows.phase_b.bw_send_max_access_count', 1));
        $deleteInDays = max(1, (int) config('intranet-app-workflows.phase_b.bw_send_delete_in_days', 7));

        if ($dry = PhaseBGuard::assertNotDryRunOrMessage(
            "Bitwarden-Send-Passwort-Mail an Vorgesetzten {$email}"
        )) {
            return ActionResult::succeeded(
                message: $dry->message,
                output: array_merge($dry->output, [
                    'supervisor_password_email' => $email,
                    'supervisor_password_user_id' => $supervisorId,
                ]),
                messages: $dry->messages,
            );
        }

        try {
            $accessUrl = $this->bitwarden->createSend(
                $sendName,
                $password,
                $maxAccess,
                $deleteInDays,
            );

            Mail::to($email)->send(new SupervisorPasswordSendMail(
                employeeName: $employeeName,
                username: $username,
                accessUrl: $accessUrl,
            ));
        } catch (Throwable $e) {
            report($e);

            return ActionResult::failed('Bitwarden-Send an Vorgesetzten fehlgeschlagen: '.$e->getMessage());
        }

        return ActionResult::succeeded(
            message: "Passwort per Bitwarden Send an Vorgesetzten {$email} gesendet",
            output: [
                'supervisor_password_email' => $email,
                'supervisor_password_user_id' => $supervisorId,
                'supervisor_password_bitwarden_sent' => true,
            ],
        );
    }
}
