<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppWorkflows\IntranetAppWorkflows;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Support\AssigneeGroups;
use Hwkdo\IntranetAppWorkflows\Support\FlowTitle;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class WorkflowAssignedGroupNotification extends IntranetNotification
{
    public function __construct(
        public readonly WorkflowFlow $flow,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'workflows.assigned_group';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = FlowTitle::for($this->flow);
        $group = $this->groupLabel();
        $step = $this->currentStepTitle();

        return (new MailMessage)
            ->subject('Workflow für Gruppe '.$group.': '.$title)
            ->line('Für die Gruppe '.$group.' liegt ein neuer Workflow zur Übernahme bereit.')
            ->line($title.($step !== null ? ' – Schritt: '.$step : ''))
            ->action('Workflow öffnen', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = FlowTitle::for($this->flow);
        $group = $this->groupLabel();
        $step = $this->currentStepTitle();

        return $this->inboxPayload(
            title: 'Workflow für Gruppe '.$group,
            body: $title.($step !== null ? ' – '.$step : '').' – bitte zuweisen/übernehmen',
            url: $this->url(),
            appIdentifier: IntranetAppWorkflows::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Workflow für Gruppe '.$this->groupLabel())
            ->body(FlowTitle::for($this->flow))
            ->data(['url' => $this->url()]);
    }

    /**
     * @return array{preview: string, topic: string, url: string}
     */
    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Workflow für Gruppe '.$this->groupLabel().': '.FlowTitle::for($this->flow),
            'topic' => IntranetAppWorkflows::app_name(),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('apps.workflows.flows.show', $this->flow);
    }

    private function groupLabel(): string
    {
        $key = (string) ($this->flow->assignee_group_key ?? '');

        return $key !== '' ? AssigneeGroups::label($key) : 'Gruppe';
    }

    private function currentStepTitle(): ?string
    {
        $this->flow->loadMissing('type.steps');

        $step = $this->flow->type?->steps
            ?->firstWhere('position', $this->flow->current_step_position);

        return $step?->title;
    }
}
