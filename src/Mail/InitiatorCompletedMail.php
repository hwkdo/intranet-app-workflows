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

class InitiatorCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $betreff,
        public readonly string $inhalt,
    ) {}

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

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->betreff,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-workflows::emails.initiator-completed',
            with: [
                'betreff' => $this->betreff,
                'inhalt' => $this->inhalt,
            ],
        );
    }
}
