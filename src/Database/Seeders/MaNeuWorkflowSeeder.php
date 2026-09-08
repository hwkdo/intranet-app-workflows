<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Database\Seeders;

use Hwkdo\IntranetAppWorkflows\Models\WorkflowAction;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowInput;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStep;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowStepAction;
use Hwkdo\IntranetAppWorkflows\Models\WorkflowType;
use Hwkdo\IntranetAppWorkflows\Support\AssigneeGroups;
use Illuminate\Support\Facades\DB;

/**
 * ma_neu Definition: Formulare + Identity (B) + Rechte/Tickets (C) + Abschluss-Mails (D).
 * Resolve-Assignee steht jeweils zuletzt.
 */
final class MaNeuWorkflowSeeder
{
    public function seed(): void
    {
        AssigneeGroups::ensureRolesExist();

        DB::transaction(function (): void {
            $type = WorkflowType::query()->updateOrCreate(
                ['key' => 'ma_neu'],
                [
                    'title' => 'Mitarbeiter Neueinstellung',
                    'description' => 'Onboarding-Workflow für neue Mitarbeitende (Identity, Rechte/Tickets, Abschluss-Mails).',
                    'is_active' => true,
                ],
            );

            $steps = [
                1 => ['key' => 'hr_init', 'title' => 'Initialisierung – HR'],
                2 => ['key' => 'vorgesetzter', 'title' => 'Vorgesetzter'],
                3 => ['key' => 'it_benutzer', 'title' => 'IT – Benutzer einrichten'],
                4 => ['key' => 'it_checkliste', 'title' => 'IT – Checkliste'],
            ];

            $stepModels = [];
            foreach ($steps as $position => $meta) {
                $stepModels[$position] = WorkflowStep::query()->updateOrCreate(
                    ['type_id' => $type->id, 'key' => $meta['key']],
                    [
                        'position' => $position,
                        'title' => $meta['title'],
                        'form_component' => null,
                    ],
                );
            }

            $this->syncStepInputs($stepModels[1], [
                ['key' => 'vorname', 'label' => 'Vorname', 'typ' => 'text', 'required' => true],
                ['key' => 'nachname', 'label' => 'Nachname', 'typ' => 'text', 'required' => true],
                ['key' => 'personalnr', 'label' => 'Personalnummer', 'typ' => 'text', 'required' => true],
                ['key' => 'einsatzab', 'label' => 'Einsatz ab', 'typ' => 'date', 'required' => true, 'infotext' => 'Erster Arbeitstag / Stichtag'],
                ['key' => 'abteilung', 'label' => 'Abteilung (GVP)', 'typ' => 'gvp_select', 'required' => true],
                ['key' => 'istausbilder', 'label' => 'Ist Ausbilder/Dozent', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'istazubi', 'label' => 'Ist Azubi', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'istpraktikant', 'label' => 'Ist Praktikant', 'typ' => 'ja_nein', 'required' => true],
            ]);

            $this->syncStepInputs($stepModels[2], [
                ['key' => 'raum', 'label' => 'Raum', 'typ' => 'text', 'required' => true, 'infotext' => 'Raumnummer laut Türschild'],
                ['key' => 'standort', 'label' => 'Standort', 'typ' => 'standort_select', 'required' => true],
                ['key' => 'telefon', 'label' => 'Telefon (Durchwahl)', 'typ' => 'text', 'required' => true],
                ['key' => 'fax', 'label' => 'Fax', 'typ' => 'text', 'required' => true],
                ['key' => 'hardware', 'label' => 'Hardware', 'typ' => 'single_select', 'required' => true, 'config' => [
                    'options' => [
                        ['value' => '1', 'label' => 'Neue Hardware benötigt'],
                        ['value' => '2', 'label' => 'Hardware übernehmen'],
                        ['value' => '3', 'label' => 'Bereits vorhanden'],
                    ],
                ]],
                ['key' => 'hardware_neu_benoetigt', 'label' => 'Neue Hardware (Details)', 'typ' => 'textarea', 'required' => false, 'config' => [
                    'visible_when' => ['hardware' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'hardware_uebernehmen_von', 'label' => 'Hardware übernehmen von', 'typ' => 'user_select', 'required' => false, 'config' => [
                    'visible_when' => ['hardware' => '2'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'bue_rechte_benoetigt', 'label' => 'BuE-Rechte benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'bue_rechte_analog_zu', 'label' => 'BuE analog zu', 'typ' => 'user_select', 'required' => false, 'config' => [
                    'visible_when' => ['bue_rechte_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'laufwerke_analog_zu', 'label' => 'Laufwerke analog zu', 'typ' => 'user_select', 'required' => false],
                ['key' => 'farbdruck_benoetigt', 'label' => 'Farbdruck benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'cms_benoetigt', 'label' => 'CMS-Account benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'bemerkungen', 'label' => 'Bemerkungen', 'typ' => 'textarea', 'required' => false],
            ]);

            $this->syncStepInputs($stepModels[3], [
                ['key' => 'username', 'label' => 'Username (AD)', 'typ' => 'text', 'required' => true, 'infotext' => 'Vorschlag aus AD oder manuell eingeben.'],
                ['key' => 'add_ldap_groups', 'label' => 'LDAP-Gruppen', 'typ' => 'ldap_groups', 'required' => false],
            ]);

            $this->syncStepInputs($stepModels[4], [
                ['key' => 'hardware_benoetigt_check', 'label' => 'Hardware erledigt', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'visible_when_in' => ['hardware' => ['1', '2']],
                    'hidden_value' => '1',
                    'nein_label' => 'Nein (Ticket)',
                ]],
                ['key' => 'bue_check', 'label' => 'BuE erledigt', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'visible_when' => ['bue_rechte_benoetigt' => '1'],
                    'hidden_value' => '1',
                    'nein_label' => 'Nein (Ticket)',
                ]],
                ['key' => 'farbdruck_check', 'label' => 'Farbdruck erledigt', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'visible_when' => ['farbdruck_benoetigt' => '1'],
                    'hidden_value' => '1',
                    'nein_label' => 'Nein (Ticket)',
                ]],
                ['key' => 'yubikey_check', 'label' => 'YubiKey eingerichtet?', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'nein_label' => 'Nein (Ticket)',
                ]],
                ['key' => 'telefon_check', 'label' => 'Telefonnummer eingerichtet?', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'nein_label' => 'Nein (Ticket)',
                ]],
            ]);

            $noop = WorkflowAction::query()->updateOrCreate(
                ['key' => 'demo.noop'],
                [
                    'title' => 'No-op (Platzhalter)',
                    'handler_key' => 'demo.noop',
                    'config' => ['label' => 'Placeholder'],
                    'is_idempotent' => true,
                ],
            );

            $resolve = WorkflowAction::query()->updateOrCreate(
                ['key' => 'core.resolve_assignee'],
                [
                    'title' => 'Workflow-Empfänger ermitteln',
                    'handler_key' => 'core.resolve_assignee',
                    'config' => ['fallback_to_initiator' => true],
                    'is_idempotent' => true,
                ],
            );

            // Actions je Step (B Identity, C Rechte/Tickets, D Mails) + Resolve zuletzt
            $placeholders = [
                1 => [['key' => 'ma_neu.step1.placeholder', 'title' => 'HR-Schritt abgeschlossen (Platzhalter)', 'handler' => 'demo.noop']],
                2 => [['key' => 'ma_neu.step2.placeholder', 'title' => 'Vorgesetzten-Schritt abgeschlossen (Platzhalter)', 'handler' => 'demo.noop']],
                3 => [
                    ['key' => 'ma_neu.create_ad_user', 'title' => 'AD-User erstellen', 'handler' => 'ma_neu.create_ad_user'],
                    ['key' => 'ma_neu.add_ldap_groups', 'title' => 'LDAP-Gruppen setzen', 'handler' => 'ma_neu.add_ldap_groups'],
                    ['key' => 'ma_neu.activate_ad_user', 'title' => 'AD-User aktivieren + Passwort', 'handler' => 'ma_neu.activate_ad_user'],
                    ['key' => 'ma_neu.enable_remote_mailbox', 'title' => 'Exchange Remote-Mailbox (nach Cloud-Provisionierung)', 'handler' => 'ma_neu.enable_remote_mailbox'],
                    ['key' => 'ma_neu.set_mailbox_quota', 'title' => 'Mailbox Quota setzen', 'handler' => 'ma_neu.set_mailbox_quota'],
                ],
                4 => [
                    ['key' => 'ma_neu.import_ldap_user', 'title' => 'Intranet-User importieren', 'handler' => 'ma_neu.import_ldap_user'],
                    ['key' => 'ma_neu.add_intranet_roles', 'title' => 'Intranet-Rollen setzen', 'handler' => 'ma_neu.add_intranet_roles'],
                    ['key' => 'ma_neu.create_tickets', 'title' => 'Tickets erstellen', 'handler' => 'ma_neu.create_tickets'],
                    ['key' => 'ma_neu.onboarding_mail', 'title' => 'Onboarding-Mail', 'handler' => 'ma_neu.onboarding_mail'],
                    ['key' => 'ma_neu.initiator_mail', 'title' => 'Mail an Initiator', 'handler' => 'ma_neu.initiator_mail'],
                    ['key' => 'ma_neu.send_supervisor_password_bitwarden', 'title' => 'Passwort per Bitwarden Send an Vorgesetzten', 'handler' => 'ma_neu.send_supervisor_password_bitwarden'],
                ],
            ];

            foreach ($placeholders as $position => $actions) {
                $step = $stepModels[$position];
                $desiredActionIds = [];

                // Unique (step_id, position): bestehende Positionen freiräumen, bevor neu sortiert wird.
                WorkflowStepAction::query()
                    ->where('step_id', $step->id)
                    ->update(['position' => DB::raw('position + 1000')]);

                $pos = 1;
                foreach ($actions as $actionMeta) {
                    $handlerKey = $actionMeta['handler'] ?? 'demo.noop';
                    $action = WorkflowAction::query()->updateOrCreate(
                        ['key' => $actionMeta['key']],
                        [
                            'title' => $actionMeta['title'],
                            'handler_key' => $handlerKey,
                            'config' => ['label' => $actionMeta['title']],
                            'is_idempotent' => true,
                        ],
                    );

                    WorkflowStepAction::query()->updateOrCreate(
                        [
                            'step_id' => $step->id,
                            'action_id' => $action->id,
                        ],
                        [
                            'position' => $pos++,
                            'config_override' => null,
                        ],
                    );
                    $desiredActionIds[] = $action->id;
                }

                $resolveConfig = match ($position) {
                    // Legacy: nach HR → GVP-Vorgesetzter (vorgesetzter_abteilung).
                    1 => [
                        'gvp_payload_key' => 'abteilung',
                        'fallback_to_initiator' => true,
                    ],
                    // Legacy: nach Vorgesetzter / IT-Benutzer → Gruppe IT (Claim nötig).
                    2, 3 => [
                        'group' => 'it',
                        'fallback_to_initiator' => false,
                    ],
                    default => [
                        'fallback_to_initiator' => true,
                    ],
                };

                WorkflowStepAction::query()->updateOrCreate(
                    [
                        'step_id' => $step->id,
                        'action_id' => $resolve->id,
                    ],
                    [
                        'position' => $pos,
                        'config_override' => $resolveConfig,
                    ],
                );
                $desiredActionIds[] = $resolve->id;

                // Orphans nur entfernen, wenn keine Action-Runs daran hängen (kein Cascade-Wipe laufender Flows).
                WorkflowStepAction::query()
                    ->where('step_id', $step->id)
                    ->whereNotIn('action_id', $desiredActionIds)
                    ->whereDoesntHave('actionRuns')
                    ->delete();

                // Gleiche Action nicht auf anderen Steps stehen lassen (z. B. nach Verschieben).
                WorkflowStepAction::query()
                    ->whereIn('action_id', $desiredActionIds)
                    ->where('step_id', '!=', $step->id)
                    ->whereDoesntHave('actionRuns')
                    ->delete();
            }

            unset($noop);
        });
    }

    /**
     * @param  list<array{key: string, label: string, typ: string, required?: bool, infotext?: string, config?: array<string, mixed>}>  $fields
     */
    private function syncStepInputs(WorkflowStep $step, array $fields): void
    {
        $step->inputs()->detach();

        foreach ($fields as $index => $field) {
            $input = WorkflowInput::query()->updateOrCreate(
                ['key' => $field['key']],
                [
                    'label' => $field['label'],
                    'typ' => $field['typ'],
                    'infotext' => $field['infotext'] ?? null,
                    'config' => $field['config'] ?? null,
                ],
            );

            $step->inputs()->attach($input->id, [
                'position' => $index + 1,
                'required' => (bool) ($field['required'] ?? false),
                'config' => null,
            ]);
        }
    }
}
