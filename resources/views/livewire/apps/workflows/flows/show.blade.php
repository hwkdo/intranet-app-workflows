<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Enums\ActionRunStatus;
use Hwkdo\IntranetAppWorkflows\Enums\FlowStatus;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowActionRun;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowFlow;
use Hwkdo\IntranetAppWorkflows\Services\FlowClaimService;
use Hwkdo\IntranetAppWorkflows\Services\MaAustrittInventoryService;
use Hwkdo\IntranetAppWorkflows\Services\MaNeuStep3Planner;
use Hwkdo\IntranetAppWorkflows\Services\MaUmsetzungRechtePlanner;
use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Hwkdo\IntranetAppWorkflows\Support\FlowAccess;
use Hwkdo\IntranetAppWorkflows\Support\GvpSupervisorResolver;
use Hwkdo\IntranetAppWorkflows\Support\MaNeuChecklistInspector;
use Hwkdo\IntranetAppWorkflows\Support\StepFormRules;
use Hwkdo\IntranetAppWorkflows\Support\WorkflowModels;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, state, title};

state([
    'flowId' => null,
    /** @var array<string, mixed> */
    'form' => [],
    'forceEdit' => false,
    'usernameAvailable' => null,
    'usernameChecked' => false,
    /** @var list<string> */
    'shareGroupsSelected' => [],
    /** @var list<string> */
    'emailGroupsSelected' => [],
    /** @var list<string> */
    'd3GroupsSelected' => [],
    /** @var list<string> */
    'shareGroupsAvailable' => [],
    /** @var list<string> */
    'emailGroupsAvailable' => [],
    /** @var list<string> */
    'd3GroupsAvailable' => [],
    'ldapAnalogError' => null,
    /** @var list<string> */
    'removeLdapGroupsSelected' => [],
    /** @var list<string> */
    'removeShareGroupsAvailable' => [],
    /** @var list<string> */
    'removeEmailGroupsAvailable' => [],
    /** @var list<string> */
    'removeD3GroupsAvailable' => [],
    'ldapCurrentError' => null,
    /** @var list<string> */
    'intranetRolesSelected' => [],
    /** @var list<string> */
    'intranetRolesAvailable' => [],
    /** @var list<string> */
    'removeIntranetRolesSelected' => [],
    /** @var list<string> */
    'removeIntranetRolesAvailable' => [],
    'currentPickupName' => '',
    'analogPickupName' => '',
    /** @var int refreshes checklist computed cache */
    'checklistRefreshToken' => 0,
    'austrittBulkDokumenteUserId' => null,
    'austrittBulkAkUserId' => null,
    'austrittBulkBwUserId' => null,
]);

$resetFormFromCurrentStep = function (MaNeuStep3Planner $planner, MaUmsetzungRechtePlanner $rechtePlanner): void {
    $flow = WorkflowFlow::query()->with(['type.steps.inputs'])->findOrFail($this->flowId);
    $step = $flow->currentStep();

    $this->form = $step
        ? StepFormRules::initialFormForStep($flow, $step)
        : [];

    $this->prepareItBenutzerStep($planner);
    $this->prepareUmsetzungRechteStep($rechtePlanner);
    $this->prepareUmsetzungVorgesetzterStep();
    $this->prepareAustrittVorgesetzterStep();
};

$prepareUmsetzungVorgesetzterStep = function (): void {
    $flow = WorkflowFlow::query()->findOrFail($this->flowId);
    $step = $flow->currentStep();
    if ($flow->type?->key !== 'ma_umsetzung' || $step?->key !== 'vorgesetzter') {
        return;
    }

    $mitarbeiterId = $flow->getPayloadValue('mitarbeiter');
    if (! is_numeric($mitarbeiterId)) {
        return;
    }

    $user = WorkflowModels::userQuery()->find((int) $mitarbeiterId);
    if (! $user) {
        return;
    }

    if (blank($this->form['telefon'] ?? null) && filled($user->telefon ?? null)) {
        $this->form['telefon'] = (string) $user->telefon;
    }
    if (blank($this->form['fax'] ?? null) && filled($user->fax ?? null)) {
        $this->form['fax'] = (string) $user->fax;
    }
    if (blank($this->form['raum'] ?? null) && filled($user->raum ?? null)) {
        $this->form['raum'] = (string) $user->raum;
    }
    if (blank($this->form['standort'] ?? null) && filled($user->standort_id ?? null)) {
        $this->form['standort'] = (string) $user->standort_id;
    }
};

$prepareAustrittVorgesetzterStep = function (): void {
    $flow = WorkflowFlow::query()->findOrFail($this->flowId);
    $step = $flow->currentStep();
    if ($flow->type?->key !== 'ma_austritt' || $step?->key !== 'vorgesetzter') {
        return;
    }

    $mitarbeiterId = $flow->getPayloadValue('mitarbeiter');
    if (! is_numeric($mitarbeiterId)) {
        return;
    }

    $inventory = app(MaAustrittInventoryService::class)->forMitarbeiter((int) $mitarbeiterId);
    $defaultStandort = $inventory['default_standort_id'];

    $existingDocs = $flow->getPayloadValue('dokumente_assignments');
    $docs = is_array($existingDocs) ? $existingDocs : [];
    foreach ($inventory['dokumente'] as $row) {
        $key = $row['key'];
        if (! isset($docs[$key]) || ! is_array($docs[$key])) {
            $docs[$key] = [
                'document_id' => $row['document_id'],
                'role' => $row['role'],
                'to_user_id' => null,
            ];
        } else {
            $docs[$key]['document_id'] = $row['document_id'];
            $docs[$key]['role'] = $row['role'];
        }
    }
    $this->form['dokumente_assignments'] = $docs;

    $existingAk = $flow->getPayloadValue('arbeitskreise_assignments');
    $aks = is_array($existingAk) ? $existingAk : [];
    foreach ($inventory['arbeitskreise'] as $row) {
        $key = $row['key'];
        if (! isset($aks[$key]) || ! is_array($aks[$key])) {
            $aks[$key] = [
                'ak_id' => $row['ak_id'],
                'role' => $row['role'],
                'action' => 'transfer',
                'to_user_id' => null,
            ];
        } else {
            $aks[$key]['ak_id'] = $row['ak_id'];
            $aks[$key]['role'] = $row['role'];
            $aks[$key]['action'] = $aks[$key]['action'] ?? 'transfer';
        }
    }
    $this->form['arbeitskreise_assignments'] = $aks;

    $existingBw = $flow->getPayloadValue('beauftragungen_assignments');
    $bws = is_array($existingBw) ? $existingBw : [];
    foreach ($inventory['beauftragungen'] as $row) {
        $key = $row['key'];
        if (! isset($bws[$key]) || ! is_array($bws[$key])) {
            $bws[$key] = [
                'bw_id' => $row['bw_id'],
                'action' => 'transfer',
                'to_user_id' => null,
            ];
        } else {
            $bws[$key]['bw_id'] = $row['bw_id'];
            $bws[$key]['action'] = $bws[$key]['action'] ?? 'transfer';
        }
    }
    $this->form['beauftragungen_assignments'] = $bws;

    $existingAssets = $flow->getPayloadValue('assets_dispositions');
    $assets = is_array($existingAssets) ? $existingAssets : [];
    foreach ($inventory['assets'] as $asset) {
        $id = (string) $asset['id'];
        if (! isset($assets[$id]) || ! is_array($assets[$id])) {
            $assets[$id] = [
                'choice' => '',
                'standort_id' => $defaultStandort,
                'datetime' => null,
                'to_user_id' => null,
            ];
        } elseif (($assets[$id]['standort_id'] ?? null) === null && $defaultStandort !== null) {
            $assets[$id]['standort_id'] = $defaultStandort;
        }
    }
    $this->form['assets_dispositions'] = $assets;
};

$prepareItBenutzerStep = function (MaNeuStep3Planner $planner): void {
    $flow = WorkflowFlow::query()->findOrFail($this->flowId);
    $step = $flow->currentStep();

    if ($step?->key !== 'it_benutzer') {
        if ($step?->key !== 'it_rechte') {
            $this->shareGroupsSelected = [];
            $this->emailGroupsSelected = [];
            $this->d3GroupsSelected = [];
            $this->shareGroupsAvailable = [];
            $this->emailGroupsAvailable = [];
            $this->d3GroupsAvailable = [];
            $this->ldapAnalogError = null;
        }
        $this->usernameAvailable = null;
        $this->usernameChecked = false;

        return;
    }

    $analogUserId = $flow->getPayloadValue('laufwerke_analog_zu');
    $analogUserId = is_numeric($analogUserId) ? (int) $analogUserId : null;
    $analog = $planner->analogGroups($analogUserId);

    $this->shareGroupsAvailable = $analog['share'];
    $this->emailGroupsAvailable = $analog['email'];
    $this->d3GroupsAvailable = $analog['d3'];
    $this->ldapAnalogError = $analog['error'];

    $existing = $flow->getPayloadValue('add_ldap_groups');
    $existingList = is_array($existing)
        ? array_values(array_filter($existing, static fn ($g): bool => is_string($g) && $g !== ''))
        : (is_string($existing) && $existing !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $existing))))
            : []);

    if ($existingList !== []) {
        $this->shareGroupsSelected = array_values(array_intersect($existingList, $this->shareGroupsAvailable));
        $this->emailGroupsSelected = array_values(array_intersect($existingList, $this->emailGroupsAvailable));
        $this->d3GroupsSelected = array_values(array_intersect($existingList, $this->d3GroupsAvailable));
    } else {
        $this->shareGroupsSelected = [];
        $this->emailGroupsSelected = [];
        $this->d3GroupsSelected = [];
    }

    if (blank($this->form['username'] ?? null)) {
        $guess = $planner->suggestUsername();
        if ($guess !== null) {
            $this->form['username'] = $guess;
            $this->usernameAvailable = $planner->isUsernameAvailable($guess);
            $this->usernameChecked = true;
        }
    } elseif (filled($this->form['username'] ?? null) && ! $this->usernameChecked) {
        $this->usernameAvailable = $planner->isUsernameAvailable((string) $this->form['username']);
        $this->usernameChecked = true;
    }
};

$prepareUmsetzungRechteStep = function (MaUmsetzungRechtePlanner $rechtePlanner): void {
    $flow = WorkflowFlow::query()->findOrFail($this->flowId);
    $step = $flow->currentStep();

    if ($step?->key !== 'it_rechte') {
        $this->removeLdapGroupsSelected = [];
        $this->removeShareGroupsAvailable = [];
        $this->removeEmailGroupsAvailable = [];
        $this->removeD3GroupsAvailable = [];
        $this->ldapCurrentError = null;
        $this->intranetRolesSelected = [];
        $this->intranetRolesAvailable = [];
        $this->removeIntranetRolesSelected = [];
        $this->removeIntranetRolesAvailable = [];
        $this->currentPickupName = '';
        $this->analogPickupName = '';

        return;
    }

    $analogUserId = $flow->getPayloadValue('laufwerke_analog_zu');
    $analogUserId = is_numeric($analogUserId) ? (int) $analogUserId : null;
    $mitarbeiterId = $flow->getPayloadValue('mitarbeiter');
    $mitarbeiterId = is_numeric($mitarbeiterId) ? (int) $mitarbeiterId : null;
    $username = trim((string) $flow->getPayloadValue('username', ''));

    $analog = $rechtePlanner->analogGroups($analogUserId);
    $this->shareGroupsAvailable = $analog['share'];
    $this->emailGroupsAvailable = $analog['email'];
    $this->d3GroupsAvailable = $analog['d3'];
    $this->ldapAnalogError = $analog['error'];

    $current = $rechtePlanner->currentGroups($username);
    $this->removeShareGroupsAvailable = $current['share'];
    $this->removeEmailGroupsAvailable = $current['email'];
    $this->removeD3GroupsAvailable = $current['d3'];
    $this->ldapCurrentError = $current['error'];

    $forced = $rechtePlanner->forcedGroups($flow);
    $filterForced = static fn (array $groups): array => array_values(array_diff($groups, $forced));
    $this->removeShareGroupsAvailable = $filterForced($this->removeShareGroupsAvailable);
    $this->removeEmailGroupsAvailable = $filterForced($this->removeEmailGroupsAvailable);
    $this->removeD3GroupsAvailable = $filterForced($this->removeD3GroupsAvailable);

    // Add-Kandidaten: Analog minus bereits Mitglied
    $currentAll = $current['all'];
    $this->shareGroupsAvailable = array_values(array_diff($this->shareGroupsAvailable, $currentAll));
    $this->emailGroupsAvailable = array_values(array_diff($this->emailGroupsAvailable, $currentAll));
    $this->d3GroupsAvailable = array_values(array_diff($this->d3GroupsAvailable, $currentAll));

    $this->intranetRolesAvailable = $rechtePlanner->analogIntranetRoles($analogUserId, $mitarbeiterId);
    $this->removeIntranetRolesAvailable = $rechtePlanner->currentIntranetRoles($mitarbeiterId);

    $existingAdd = $flow->getPayloadValue('add_ldap_groups');
    $existingAddList = is_array($existingAdd)
        ? array_values(array_filter($existingAdd, static fn ($g): bool => is_string($g) && $g !== ''))
        : [];
    $this->shareGroupsSelected = array_values(array_intersect($existingAddList, $this->shareGroupsAvailable));
    $this->emailGroupsSelected = array_values(array_intersect($existingAddList, $this->emailGroupsAvailable));
    $this->d3GroupsSelected = array_values(array_intersect($existingAddList, $this->d3GroupsAvailable));

    $existingRemove = $flow->getPayloadValue('remove_ldap_groups');
    $existingRemoveList = is_array($existingRemove)
        ? array_values(array_filter($existingRemove, static fn ($g): bool => is_string($g) && $g !== ''))
        : [];
    $removePool = array_merge(
        $this->removeShareGroupsAvailable,
        $this->removeEmailGroupsAvailable,
        $this->removeD3GroupsAvailable,
    );
    $this->removeLdapGroupsSelected = array_values(array_intersect($existingRemoveList, $removePool));

    $existingRolesAdd = $flow->getPayloadValue('add_intranet_roles');
    $this->intranetRolesSelected = is_array($existingRolesAdd)
        ? array_values(array_intersect($existingRolesAdd, $this->intranetRolesAvailable))
        : [];

    $existingRolesRemove = $flow->getPayloadValue('remove_intranet_roles');
    $this->removeIntranetRolesSelected = is_array($existingRolesRemove)
        ? array_values(array_intersect($existingRolesRemove, $this->removeIntranetRolesAvailable))
        : [];

    $cisco = app(CiscoPickupGatewayInterface::class);
    $maUser = $mitarbeiterId ? WorkflowModels::userQuery()->find($mitarbeiterId) : null;
    $currentPickup = $maUser ? $cisco->getPickupGroupForUser($maUser) : null;
    $this->currentPickupName = trim((string) ($currentPickup['name'] ?? ''));

    $pickupAnalogId = $flow->getPayloadValue('anrufuebernahme_analog_zu');
    $pickupAnalogId = is_numeric($pickupAnalogId) ? (int) $pickupAnalogId : null;
    $analogUser = $pickupAnalogId ? WorkflowModels::userQuery()->find($pickupAnalogId) : null;
    $analogPickup = $analogUser ? $cisco->getPickupGroupForUser($analogUser) : null;
    $this->analogPickupName = trim((string) ($analogPickup['name'] ?? ''));
};

mount(function (WorkflowFlow $flow, MaNeuStep3Planner $planner, MaUmsetzungRechtePlanner $rechtePlanner): void {
    abort_unless(FlowAccess::canView($flow, Auth::user()), 403);

    $this->flowId = $flow->id;
    $this->resetFormFromCurrentStep($planner, $rechtePlanner);
});

$flow = computed(fn () => WorkflowFlow::query()
    ->with([
        'type.steps.inputs',
        'type.steps.stepActions.action',
        'actionRuns.stepAction.action',
        'actionRuns.stepAction.step',
        'actionRuns.attempts',
        'histories',
        'initiator',
        'assignee',
    ])
    ->findOrFail($this->flowId));

$pageTitle = computed(function (): string {
    $flow = $this->flow;
    $name = trim(($flow->getPayloadValue('vorname') ?? '').' '.($flow->getPayloadValue('nachname') ?? ''));
    if ($name === '') {
        $name = \Hwkdo\IntranetAppWorkflows\Support\FlowTitle::for($flow);
        $name = str_contains($name, ': ') ? trim(explode(': ', $name, 2)[1] ?? '') : '';
    }

    return $name !== '' ? 'Workflow: '.$name : 'Workflow #'.$flow->id;
});

title('Workflow-Detail');

$currentStep = computed(fn () => $this->flow->currentStep());

$canEdit = computed(fn (): bool => FlowAccess::canEdit($this->flow, Auth::user()));

$canForceEdit = computed(fn (): bool => FlowAccess::canForceEdit($this->flow, Auth::user()));

$showStepForm = computed(fn (): bool => $this->canEdit || ($this->canForceEdit && $this->forceEdit));

$isCompleted = computed(fn (): bool => $this->flow->status === FlowStatus::Completed);

$isItBenutzerStep = computed(fn (): bool => $this->currentStep?->key === 'it_benutzer');

$isItRechteStep = computed(fn (): bool => $this->currentStep?->key === 'it_rechte');

$isItChecklisteStep = computed(fn (): bool => $this->currentStep?->key === 'it_checkliste');

$isMaUmsetzung = computed(fn (): bool => $this->flow->type?->key === 'ma_umsetzung');

$isMaAustritt = computed(fn (): bool => $this->flow->type?->key === 'ma_austritt');

$isAustrittVorgesetzterStep = computed(
    fn (): bool => $this->isMaAustritt && $this->currentStep?->key === 'vorgesetzter'
);

$austrittInventory = computed(function (): array {
    if (! $this->isAustrittVorgesetzterStep) {
        return ['dokumente' => [], 'arbeitskreise' => [], 'beauftragungen' => [], 'assets' => [], 'default_standort_id' => null];
    }
    $mitarbeiterId = $this->flow->getPayloadValue('mitarbeiter');
    if (! is_numeric($mitarbeiterId)) {
        return ['dokumente' => [], 'arbeitskreise' => [], 'beauftragungen' => [], 'assets' => [], 'default_standort_id' => null];
    }

    return app(MaAustrittInventoryService::class)->forMitarbeiter((int) $mitarbeiterId);
});

$austrittActiveUsers = computed(function () {
    $mitarbeiterId = (int) ($this->flow->getPayloadValue('mitarbeiter') ?? 0);

    return WorkflowModels::activeUsersForSelect()
        ->filter(fn ($u): bool => (int) $u->id !== $mitarbeiterId)
        ->values();
});

$austrittStandorte = computed(fn () => \App\Models\Standort::query()->orderBy('name')->get());

$checklistFormContext = computed(function (): array {
    return array_merge($this->flow->payload ?? [], $this->form);
});

$checklistStatus = computed(function (): array {
    $this->checklistRefreshToken;

    $status = app(MaNeuChecklistInspector::class)->inspect($this->flow);

    if (! $status['bitwarden_sent'] && $status['bitwarden_email'] === '') {
        $supervisorId = GvpSupervisorResolver::userIdForAbteilung($this->flow->getPayloadValue('abteilung'));
        if ($supervisorId !== null) {
            $supervisor = WorkflowModels::userQuery()->find($supervisorId);
            $status['bitwarden_email'] = trim((string) ($supervisor?->email ?? ''));
        }
    }

    return $status;
});

$umsetzungChecklistStatus = computed(function (): array {
    $this->checklistRefreshToken;

    return app(MaNeuChecklistInspector::class)->inspect($this->flow);
});

$forcedLdapGroups = computed(function (): array {
    if ($this->isItBenutzerStep) {
        return app(MaNeuStep3Planner::class)->forcedGroups($this->flow);
    }

    if ($this->isItRechteStep) {
        return app(MaUmsetzungRechtePlanner::class)->forcedGroups($this->flow);
    }

    return [];
});

$liveFieldKeys = computed(function (): array {
    $step = $this->currentStep;

    return $step ? StepFormRules::liveDependencyKeys($step->inputs) : [];
});

$runsForStep = function (int $stepId) {
    return $this->flow->actionRuns
        ->filter(fn (WorkflowActionRun $run): bool => (int) $run->stepAction?->step_id === $stepId)
        ->sortBy('position')
        ->values();
};

$suggestUsername = function (MaNeuStep3Planner $planner): void {
    $guess = $planner->suggestUsername();
    if ($guess === null) {
        Flux::toast(variant: 'warning', text: 'Kein Username-Vorschlag möglich (LDAP?).');

        return;
    }

    $this->form['username'] = $guess;
    $this->usernameAvailable = $planner->isUsernameAvailable($guess);
    $this->usernameChecked = true;
};

$checkUsernameAvailability = function (MaNeuStep3Planner $planner): void {
    $username = trim((string) ($this->form['username'] ?? ''));
    if ($username === '') {
        $this->addError('form.username', 'Username ist erforderlich.');

        return;
    }

    $this->usernameAvailable = $planner->isUsernameAvailable($username);
    $this->usernameChecked = true;

    if ($this->usernameAvailable === null) {
        Flux::toast(variant: 'warning', text: 'LDAP-Prüfung fehlgeschlagen.');
    }
};

$refreshChecklistStatus = function (): void {
    $this->checklistRefreshToken++;
    Flux::toast(variant: 'success', text: 'Prüfungen aktualisiert.');
};

$updatedForm = function (mixed $value, ?string $key = null): void {
    if ($key === 'username') {
        $this->usernameAvailable = null;
        $this->usernameChecked = false;
    }
};

$enableForceEdit = function (): void {
    abort_unless(FlowAccess::canForceEdit($this->flow, Auth::user()), 403);
    $this->forceEdit = true;
};

$cancelForceEdit = function (): void {
    $this->forceEdit = false;
};

$austrittBulkAssignDokumente = function (): void {
    $uid = $this->austrittBulkDokumenteUserId;
    if (! is_numeric($uid)) {
        return;
    }
    $docs = $this->form['dokumente_assignments'] ?? [];
    foreach ($docs as $key => $row) {
        if (! is_array($row)) {
            continue;
        }
        $docs[$key]['to_user_id'] = (int) $uid;
    }
    $this->form['dokumente_assignments'] = $docs;
};

$austrittBulkAssignArbeitskreise = function (): void {
    $uid = $this->austrittBulkAkUserId;
    if (! is_numeric($uid)) {
        return;
    }
    $aks = $this->form['arbeitskreise_assignments'] ?? [];
    foreach ($aks as $key => $row) {
        if (! is_array($row)) {
            continue;
        }
        $aks[$key]['action'] = 'transfer';
        $aks[$key]['to_user_id'] = (int) $uid;
    }
    $this->form['arbeitskreise_assignments'] = $aks;
};

$austrittBulkAssignBeauftragungen = function (): void {
    $uid = $this->austrittBulkBwUserId;
    if (! is_numeric($uid)) {
        return;
    }
    $bws = $this->form['beauftragungen_assignments'] ?? [];
    foreach ($bws as $key => $row) {
        if (! is_array($row)) {
            continue;
        }
        $bws[$key]['action'] = 'transfer';
        $bws[$key]['to_user_id'] = (int) $uid;
    }
    $this->form['beauftragungen_assignments'] = $bws;
};

$validateAustrittVorgesetzterInventory = function (): bool {
    $ok = true;
    foreach ($this->form['dokumente_assignments'] ?? [] as $key => $row) {
        if (! is_array($row) || ! is_numeric($row['to_user_id'] ?? null)) {
            $this->addError('form.dokumente_assignments.'.$key.'.to_user_id', 'Bitte Nachfolger wählen.');
            $ok = false;
        }
    }
    foreach ($this->form['arbeitskreise_assignments'] ?? [] as $key => $row) {
        if (! is_array($row)) {
            continue;
        }
        $action = (string) ($row['action'] ?? 'transfer');
        if ($action !== 'remove' && ! is_numeric($row['to_user_id'] ?? null)) {
            $this->addError('form.arbeitskreise_assignments.'.$key.'.to_user_id', 'Bitte Nachfolger wählen oder entfernen.');
            $ok = false;
        }
    }
    foreach ($this->form['beauftragungen_assignments'] ?? [] as $key => $row) {
        if (! is_array($row)) {
            continue;
        }
        $action = (string) ($row['action'] ?? 'transfer');
        if ($action !== 'remove' && ! is_numeric($row['to_user_id'] ?? null)) {
            $this->addError('form.beauftragungen_assignments.'.$key.'.to_user_id', 'Bitte Nachfolger wählen oder entfernen.');
            $ok = false;
        }
    }
    foreach ($this->form['assets_dispositions'] ?? [] as $aid => $row) {
        if (! is_array($row)) {
            continue;
        }
        $choice = (string) ($row['choice'] ?? '');
        if ($choice === '') {
            $this->addError('form.assets_dispositions.'.$aid.'.choice', 'Bitte Disposition wählen.');
            $ok = false;

            continue;
        }
        if ($choice === 'verbleibt_arbeitsplatz' && ! is_numeric($row['standort_id'] ?? null)) {
            $this->addError('form.assets_dispositions.'.$aid.'.standort_id', 'Standort erforderlich.');
            $ok = false;
        }
        if (in_array($choice, ['an_it', 'werde_ich_erhalten'], true) && blank($row['datetime'] ?? null)) {
            $this->addError('form.assets_dispositions.'.$aid.'.datetime', 'Datum/Uhrzeit erforderlich.');
            $ok = false;
        }
        if ($choice === 'von_anderem' && ! is_numeric($row['to_user_id'] ?? null)) {
            $this->addError('form.assets_dispositions.'.$aid.'.to_user_id', 'Mitarbeiter erforderlich.');
            $ok = false;
        }
    }

    return $ok;
};

$submit = function (WorkflowOrchestrator $orchestrator, MaNeuStep3Planner $planner, MaUmsetzungRechtePlanner $rechtePlanner): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canSubmit($flow, Auth::user()), 403);

    if ($flow->status === FlowStatus::Completed) {
        Flux::toast(variant: 'warning', text: 'Workflow ist bereits abgeschlossen.');

        return;
    }

    $step = $flow->currentStep();
    abort_unless($step !== null, 404);

    if ($flow->type?->key === 'ma_austritt' && $step->key === 'vorgesetzter') {
        if (! $this->validateAustrittVorgesetzterInventory()) {
            return;
        }
    }

    if ($step->key === 'it_benutzer') {
        $this->validate([
            'form.username' => ['required', 'string', 'min:3', 'max:64'],
        ], [
            'form.username.required' => 'Username ist erforderlich.',
        ]);

        if ($this->usernameAvailable === false) {
            $this->addError('form.username', 'Username ist belegt – bitte anderen wählen.');

            return;
        }

        if (! $this->usernameChecked) {
            $this->usernameAvailable = $planner->isUsernameAvailable((string) $this->form['username']);
            $this->usernameChecked = true;
            if ($this->usernameAvailable === false) {
                $this->addError('form.username', 'Username ist belegt – bitte anderen wählen.');

                return;
            }
        }

        $this->form['add_ldap_groups'] = $planner->mergeSelectedGroups(
            $this->shareGroupsSelected,
            $this->emailGroupsSelected,
            $this->d3GroupsSelected,
            $planner->forcedGroups($flow),
        );
    } elseif ($step->key === 'it_rechte') {
        $context = array_merge($flow->payload ?? [], $this->form);
        if ((string) ($context['anrufuebernahme_benoetigt'] ?? '') === '1') {
            $this->validate([
                'form.pickup_uebernehmen' => ['required', 'in:0,1'],
            ], [
                'form.pickup_uebernehmen.required' => 'Bitte entscheiden, ob die Anrufübernahmegruppe übernommen wird.',
            ]);
        }

        $this->form['add_ldap_groups'] = $rechtePlanner->mergeSelectedGroups(
            $this->shareGroupsSelected,
            $this->emailGroupsSelected,
            $this->d3GroupsSelected,
            $rechtePlanner->forcedGroups($flow),
        );
        $this->form['remove_ldap_groups'] = array_values(array_unique($this->removeLdapGroupsSelected));
        $this->form['add_intranet_roles'] = array_values(array_unique($this->intranetRolesSelected));
        $this->form['remove_intranet_roles'] = array_values(array_unique($this->removeIntranetRolesSelected));

        if ((string) ($this->form['pickup_uebernehmen'] ?? '') === '1' && $this->analogPickupName !== '') {
            $this->form['add_pickup_group'] = $this->analogPickupName;
        } else {
            $this->form['add_pickup_group'] = '';
        }
    } elseif ($step->key === 'it_checkliste') {
        if ($flow->type?->key === 'ma_neu') {
            $status = app(MaNeuChecklistInspector::class)->inspect($flow);
            if ($status['ad_enabled'] !== true) {
                Flux::toast(variant: 'danger', text: 'AD-User ist nicht aktiviert – bitte prüfen und erneut versuchen.');

                return;
            }
        }

        $context = array_merge($flow->payload ?? [], $this->form);
        [$rules, $messages] = StepFormRules::validation($step->inputs, $context);

        if ($rules !== []) {
            $this->validate($rules, $messages);
        }

        $pruned = StepFormRules::pruneHidden($step->inputs, $context);
        $this->form = collect($step->inputs)
            ->mapWithKeys(fn ($input) => [$input->key => $pruned[$input->key] ?? ''])
            ->all();
    } else {
        [$rules, $messages] = StepFormRules::validation($step->inputs, $this->form);

        if ($rules !== []) {
            $this->validate($rules, $messages);
        }

        $this->form = StepFormRules::pruneHidden($step->inputs, $this->form);
    }

    $updated = $orchestrator->submitStep($flow, $this->form, (int) Auth::id());

    $this->forceEdit = false;

    Flux::toast(
        variant: 'success',
        text: $updated->status === FlowStatus::Completed
            ? 'Workflow abgeschlossen.'
            : 'Schritt gespeichert – weiter zu Schritt '.$updated->current_step_position.'.',
    );

    $this->resetFormFromCurrentStep($planner, $rechtePlanner);
};

$retryRun = function (int $runId, WorkflowOrchestrator $orchestrator): void {
    abort_unless(Auth::user()?->can('manage-app-workflows'), 403);

    $run = WorkflowActionRun::query()->findOrFail($runId);
    abort_unless((int) $run->flow_id === (int) $this->flowId, 404);

    $orchestrator->retryActionRun($run);
    Flux::toast(variant: 'success', text: 'Aktion erneut ausgeführt.');
};

$claim = function (FlowClaimService $claims): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canClaim($flow, Auth::user()), 403);
    $claims->claim($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Workflow dir zugewiesen.');
};

$release = function (FlowClaimService $claims): void {
    $flow = $this->flow;
    abort_unless(FlowAccess::canRelease($flow, Auth::user()), 403);
    $claims->release($flow, Auth::user());
    Flux::toast(variant: 'success', text: 'Zuweisung gelöscht – wieder Gruppen-Pool.');
};

$canClaim = computed(fn (): bool => FlowAccess::canClaim($this->flow, Auth::user()));

$canRelease = computed(fn (): bool => FlowAccess::canRelease($this->flow, Auth::user()));

$statusColor = function (ActionRunStatus $status): string {
    return match ($status) {
        ActionRunStatus::Succeeded, ActionRunStatus::Skipped => 'green',
        ActionRunStatus::Failed => 'red',
        ActionRunStatus::Partial, ActionRunStatus::Waiting => 'amber',
        ActionRunStatus::Running, ActionRunStatus::Queued => 'blue',
        default => 'zinc',
    };
};

?>

<div>
    <x-intranet-app-workflows::workflows-layout :heading="$this->pageTitle" subheading="Flow-Detail">
        <div class="space-y-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge>{{ $this->flow->type?->title }}</flux:badge>
                        <flux:badge :color="match($this->flow->status) {
                            FlowStatus::Active => 'blue',
                            FlowStatus::Completed => 'green',
                            default => 'zinc',
                        }">{{ $this->flow->status->value }}</flux:badge>
                        @if($this->flow->due_date)
                            <flux:badge color="zinc">Stichtag {{ $this->flow->due_date->format('d.m.Y') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="text-sm text-zinc-500">
                        Initiator: {{ $this->flow->initiator?->name ?? '—' }}
                        · Assignee: {{ FlowAccess::assigneeLabel($this->flow) }}
                    </flux:text>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($this->canClaim)
                        <flux:button variant="primary" wire:click="claim" icon="hand-raised">Mir zuweisen</flux:button>
                    @endif
                    @if($this->canRelease)
                        <flux:button variant="ghost" wire:click="release" icon="x-mark">Zuweisung löschen</flux:button>
                    @endif
                    @if($this->canForceEdit && ! $this->forceEdit)
                        <flux:button variant="danger" wire:click="enableForceEdit" icon="wrench-screwdriver">
                            Notfall-Bearbeitung
                        </flux:button>
                    @endif
                    @if($this->canForceEdit && $this->forceEdit)
                        <flux:button variant="ghost" wire:click="cancelForceEdit" icon="x-mark">
                            Notfall abbrechen
                        </flux:button>
                    @endif
                    <flux:button :href="route('apps.workflows.index')" wire:navigate variant="ghost" icon="arrow-left">Zur Liste</flux:button>
                </div>
            </div>

            @if($this->flow->error_summary)
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>Markierte Aktionsfehler</flux:callout.heading>
                    <flux:callout.text>
                        <pre class="whitespace-pre-wrap text-sm">{{ $this->flow->error_summary }}</pre>
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($this->flow->type->steps->sortBy('position') as $step)
                    @php
                        $done = $this->isCompleted || $step->position < $this->flow->current_step_position;
                        $current = ! $this->isCompleted && $step->position === $this->flow->current_step_position;
                    @endphp
                    <div @class([
                        'rounded-lg border p-3 text-zinc-50',
                        'border-emerald-400/50 bg-emerald-950/55' => $done,
                        'border-sky-400/60 bg-sky-950/60 ring-1 ring-sky-400/35' => $current,
                        'border-white/15 bg-black/20' => ! $done && ! $current,
                    ])>
                        <p @class([
                            'text-xs font-medium uppercase tracking-wide',
                            'text-emerald-200/80' => $done,
                            'text-sky-200/90' => $current,
                            'text-zinc-400' => ! $done && ! $current,
                        ])>Schritt {{ $step->position }}</p>
                        <p class="mt-0.5 text-sm font-semibold text-zinc-50">{{ $step->title }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @forelse($this->runsForStep($step->id) as $run)
                                <flux:badge size="sm" :color="$this->statusColor($run->status)" :title="$run->latest_message">
                                    {{ $run->stepAction?->action?->title ?? 'Aktion' }}
                                </flux:badge>
                            @empty
                                @if($done)
                                    <flux:badge size="sm" color="zinc">erledigt</flux:badge>
                                @elseif($current)
                                    <flux:badge size="sm" color="sky">offen</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">ausstehend</flux:badge>
                                @endif
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            @if($this->isCompleted)
                <flux:callout icon="check-circle" variant="success">
                    <flux:callout.heading>Workflow abgeschlossen</flux:callout.heading>
                    <flux:callout.text>Alle Schritte sind durchlaufen. Der Workflow ist abgeschlossen.</flux:callout.text>
                </flux:callout>
            @elseif($this->showStepForm && $this->currentStep)
                <form wire:submit="submit" @class([
                    'mx-auto space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700',
                    'max-w-3xl' => $this->isItBenutzerStep || $this->isItRechteStep || $this->isItChecklisteStep,
                    'max-w-2xl' => ! $this->isItBenutzerStep && ! $this->isItRechteStep && ! $this->isItChecklisteStep,
                ])>
                    <flux:heading size="lg">Aktueller Schritt: {{ $this->currentStep->title }}</flux:heading>

                    @if($this->forceEdit && $this->canForceEdit)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.heading>Notfall-Bearbeitung aktiv</flux:callout.heading>
                            <flux:callout.text>
                                Du greifst als Manager in einen Schritt ein, der eigentlich bei
                                {{ FlowAccess::assigneeLabel($this->flow) }} liegt.
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if($this->isItBenutzerStep)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-it-benutzer')
                    @elseif($this->isAustrittVorgesetzterStep)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-austritt-vorgesetzter')
                    @elseif($this->isItRechteStep)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-umsetzung-rechte')
                    @elseif($this->isItChecklisteStep && $this->isMaUmsetzung)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-umsetzung-checkliste')
                    @elseif($this->isItChecklisteStep && $this->isMaAustritt)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-austritt-checkliste')
                    @elseif($this->isItChecklisteStep)
                        @include('intranet-app-workflows::livewire.apps.workflows.flows.partials.step-it-checkliste')
                    @else
                        <div class="space-y-4">
                            @foreach($this->currentStep->inputs as $input)
                                @continue(! StepFormRules::isVisible($input, $this->form))
                                @continue($input->typ === 'ldap_groups')
                                <div wire:key="show-input-{{ $input->key }}">
                                    <x-intranet-app-workflows::step-field
                                        :input="$input"
                                        :live="in_array($input->key, $this->liveFieldKeys, true)"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end gap-2">
                        @if($this->forceEdit && $this->canForceEdit)
                            <flux:button type="button" variant="ghost" wire:click="cancelForceEdit">Abbrechen</flux:button>
                        @endif
                        <flux:button type="submit" variant="primary" icon="check">
                            @if($this->isItChecklisteStep && $this->isMaAustritt)
                                Freigeben &amp; Stichtag planen
                            @else
                                Speichern &amp; weiter
                            @endif
                        </flux:button>
                    </div>
                </form>
            @else
                <flux:callout icon="lock-closed">
                    <flux:callout.heading>Nur Status-Ansicht</flux:callout.heading>
                    <flux:callout.text>
                        @if(filled($this->flow->assignee_group_key) && $this->flow->assignee_user_id === null)
                            Dieser Schritt liegt bei der Gruppe {{ FlowAccess::assigneeLabel($this->flow) }}.
                            Bitte zuerst „Mir zuweisen“, wenn du Mitglied bist.
                        @elseif((int) $this->flow->initiator_id === (int) Auth::id())
                            Als Initiator kannst du den Fortschritt verfolgen.
                            Der aktuelle Schritt ist bei {{ FlowAccess::assigneeLabel($this->flow) }}.
                            @if($this->canForceEdit)
                                Im Notfall kannst du oben „Notfall-Bearbeitung“ nutzen.
                            @endif
                        @else
                            Dieser Schritt ist {{ $this->flow->assignee?->name ? 'bei '.$this->flow->assignee->name : 'nicht dir zugewiesen' }}.
                            @if($this->canForceEdit)
                                Im Notfall kannst du oben „Notfall-Bearbeitung“ nutzen.
                            @endif
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="space-y-3">
                <flux:heading size="md">Action-Runs</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Pos</flux:table.column>
                        <flux:table.column>Schritt</flux:table.column>
                        <flux:table.column>Aktion</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Meldung</flux:table.column>
                        <flux:table.column>Attempts</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->flow->actionRuns->sortBy([
                            fn ($r) => $r->stepAction?->step?->position ?? 0,
                            fn ($r) => $r->position,
                        ]) as $run)
                            <flux:table.row wire:key="run-{{ $run->id }}">
                                <flux:table.cell>{{ $run->position }}</flux:table.cell>
                                <flux:table.cell>{{ $run->stepAction?->step?->position ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $run->stepAction?->action?->title }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$this->statusColor($run->status)">{{ $run->status->value }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-lg">
                                    <div class="space-y-1">
                                        <div class="truncate" title="{{ $run->latest_message }}">{{ $run->latest_message }}</div>
                                        @php($detailLines = $run->detailLines())
                                        @if($detailLines !== [])
                                            <details class="text-xs text-zinc-500 dark:text-zinc-400">
                                                <summary class="cursor-pointer select-none">Details ({{ count($detailLines) }})</summary>
                                                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                                    @foreach($detailLines as $line)
                                                        <li class="break-all">{{ $line }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $run->attempts->count() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if(Auth::user()?->can('manage-app-workflows') && in_array($run->status, [ActionRunStatus::Failed, ActionRunStatus::Partial], true))
                                        <flux:button size="sm" variant="ghost" wire:click="retryRun({{ $run->id }})">Retry</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </x-intranet-app-workflows::workflows-layout>
</div>
