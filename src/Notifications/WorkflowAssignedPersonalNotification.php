<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class WorkflowAssignedPersonalNotification extends IntranetNotification
{
    public function __construct(
        public readonly WorkflowFlow $flow,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'workflows.assigned_personal';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = FlowTitle::for($this->flow);
        $step = $this->currentStepTitle();

        return (new MailMessage)
            ->subject('Workflow zugewiesen: '.$title)
            ->line('Dir wurde ein Workflow zur Bearbeitung zugewiesen.')
            ->line($title.($step !== null ? ' – Schritt: '.$step : ''))
            ->action('Workflow öffnen', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = FlowTitle::for($this->flow);
        $step = $this->currentStepTitle();

        return $this->inboxPayload(
            title: 'Workflow zugewiesen',
            body: $title.($step !== null ? ' – '.$step : ''),
            url: $this->url(),
            appIdentifier: IntranetAppWorkflows::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Workflow zugewiesen')
            ->body(FlowTitle::for($this->flow))
            ->data(['url' => $this->url()]);
    }

    /**
     * @return array{preview: string, topic: string, url: string}
     */
    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Workflow zugewiesen: '.FlowTitle::for($this->flow),
            'topic' => IntranetAppWorkflows::app_name(),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('apps.workflows.flows.show', $this->flow);
    }

    private function currentStepTitle(): ?string
    {
        $this->flow->loadMissing('type.steps');

        $step = $this->flow->type?->steps
            ?->firstWhere('position', $this->flow->current_step_position);

        return $step?->title;
    }
}
