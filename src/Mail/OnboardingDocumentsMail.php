<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Kein Eloquent-Model serialisieren: Step-4 läuft in einer DB-Transaction,
 * Horizon kann die Mail sonst vor dem Commit des frisch importierten Users verarbeiten.
 */
class OnboardingDocumentsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $link,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $fromAddress = (string) config(
            'intranet-app-workflows.phase_d.mail_absender',
            config('intranet-app-workflows.phase_c.ticket_absender', 'mailing@hwk-do.de'),
        );
        $fromName = (string) config(
            'intranet-app-workflows.phase_d.mail_absender_name',
            config('intranet-app-workflows.phase_c.ticket_absender_name', 'Intranet Workflows'),
        );
        $subject = (string) config(
            'intranet-app-workflows.phase_d.onboarding_subject',
            'Onboarding Dokumente!',
        );

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-workflows::emails.onboarding-documents',
            with: [
                'link' => $this->link,
            ],
        );
    }
}
