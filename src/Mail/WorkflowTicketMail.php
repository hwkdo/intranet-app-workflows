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

class WorkflowTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payloadDump
     */
    public function __construct(
        public readonly string $betreff,
        public readonly string $inhalt,
        public readonly array $payloadDump = [],
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('intranet-app-workflows.phase_c.ticket_absender', 'mailing@hwk-do.de');
        $fromName = (string) config('intranet-app-workflows.phase_c.ticket_absender_name', 'Intranet Workflows');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $this->betreff,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'intranet-app-workflows::emails.workflow-ticket',
            with: [
                'betreff' => $this->betreff,
                'inhalt' => $this->inhalt,
                'dump' => $this->payloadDump,
            ],
        );
    }
}
