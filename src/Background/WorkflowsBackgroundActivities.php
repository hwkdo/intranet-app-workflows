<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows;
use Hwkdo\IntranetAppWorkflows\Mail\InitiatorCompletedMail;
use Hwkdo\IntranetAppWorkflows\Mail\OnboardingDocumentsMail;
use Hwkdo\IntranetAppWorkflows\Mail\SupervisorPasswordSendMail;
use Hwkdo\IntranetAppWorkflows\Mail\WorkflowTicketMail;

final class WorkflowsBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppWorkflows::identifier();
        $name = IntranetAppWorkflows::app_name();
        $all = BackgroundActivityCadence::cases();

        return [
            BackgroundActivityDefinition::scheduled(
                key: 'workflows.process-waiting-actions',
                label: 'Wartende Aktionen',
                appIdentifier: $id,
                appName: $name,
                command: 'workflows:process-waiting-actions',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Verarbeitet fällige wartende ActionRuns.',
                allowedCadences: $all,
                minEvery: 1,
                maxEvery: 60,
                sort: 10,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'workflows.azubi-rotation',
                label: 'Azubi-Rotationen',
                appIdentifier: $id,
                appName: $name,
                command: 'workflows:azubi-rotation-check',
                frequency: BackgroundActivityFrequency::dailyAt('01:10'),
                description: 'Startet Azubi-Rotationen für Einsätze am nächsten Tag.',
                allowedCadences: $all,
                sort: 20,
            ),
            BackgroundActivityDefinition::mail(key: 'workflows.mail-ticket', label: 'Mail: Ticket', appIdentifier: $id, appName: $name, mailable: WorkflowTicketMail::class, sort: 30),
            BackgroundActivityDefinition::mail(key: 'workflows.mail-initiator', label: 'Mail: Abschluss an Initiator', appIdentifier: $id, appName: $name, mailable: InitiatorCompletedMail::class, sort: 40),
            BackgroundActivityDefinition::mail(key: 'workflows.mail-onboarding', label: 'Mail: Onboarding', appIdentifier: $id, appName: $name, mailable: OnboardingDocumentsMail::class, sort: 50),
            BackgroundActivityDefinition::mail(key: 'workflows.mail-supervisor-password', label: 'Mail: Vorgesetzten-Passwort', appIdentifier: $id, appName: $name, mailable: SupervisorPasswordSendMail::class, sort: 60),
        ];
    }
}
