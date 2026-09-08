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
 * ma_umsetzung: HR → Vorgesetzter → IT-Rechte → Checkliste (+ Stichtag-Actions).
 */
final class MaUmsetzungWorkflowSeeder
{
    public function seed(): void
    {
        AssigneeGroups::ensureRolesExist();

        DB::transaction(function (): void {
            $type = WorkflowType::query()->updateOrCreate(
                ['key' => 'ma_umsetzung'],
                [
                    'title' => 'Mitarbeiter Umsetzung',
                    'description' => 'Versetzung bestehender Mitarbeitender (Rechte ±, Pickup, Stammdaten, Tickets).',
                    'is_active' => true,
                ],
            );

            $steps = [
                1 => ['key' => 'hr_init', 'title' => 'Initialisierung – HR'],
                2 => ['key' => 'vorgesetzter', 'title' => 'Vorgesetzter'],
                3 => ['key' => 'it_rechte', 'title' => 'IT – Rechte'],
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
                ['key' => 'mitarbeiter', 'label' => 'Mitarbeiter', 'typ' => 'user_select', 'required' => true],
                ['key' => 'einsatzab', 'label' => 'Einsatz ab', 'typ' => 'date', 'required' => true, 'infotext' => 'Erster Tag in der neuen Abteilung / Stichtag'],
                ['key' => 'abteilung', 'label' => 'Neue Abteilung (GVP)', 'typ' => 'gvp_select', 'required' => true],
            ]);

            $this->syncStepInputs($stepModels[2], [
                ['key' => 'raum', 'label' => 'Raum', 'typ' => 'text', 'required' => true, 'infotext' => 'Raumnummer laut Türschild'],
                ['key' => 'standort', 'label' => 'Standort', 'typ' => 'standort_select', 'required' => true],
                ['key' => 'telefon', 'label' => 'Telefon (Durchwahl)', 'typ' => 'text', 'required' => true],
                ['key' => 'fax', 'label' => 'Fax', 'typ' => 'text', 'required' => true],
                ['key' => 'unterstuetzung_it_benoetigt', 'label' => 'Unterstützung IT benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'unterstuetzung_it_text', 'label' => 'Nachricht an IT', 'typ' => 'textarea', 'required' => false, 'config' => [
                    'visible_when' => ['unterstuetzung_it_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'unterstuetzung_hausmeister_benoetigt', 'label' => 'Unterstützung Hausmeister benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'unterstuetzung_hausmeister_text', 'label' => 'Nachricht an Hausmeister', 'typ' => 'textarea', 'required' => false, 'config' => [
                    'visible_when' => ['unterstuetzung_hausmeister_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
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
                ['key' => 'laufwerke_analog_zu', 'label' => 'Laufwerke / Rechte analog zu', 'typ' => 'user_select', 'required' => false],
                ['key' => 'farbdruck_benoetigt', 'label' => 'Farbdruck benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'cms_benoetigt', 'label' => 'CMS-Account benötigt', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'anrufuebernahme_benoetigt', 'label' => 'Anrufübernahmegruppe benötigt', 'typ' => 'ja_nein', 'required' => true, 'infotext' => 'Mitarbeiter innerhalb einer Anrufübernahmegruppe können die Anrufe der Kollegen sehen und übernehmen.', 'config' => [
                    'default' => '',
                ]],
                ['key' => 'anrufuebernahme_analog_zu', 'label' => 'Anrufübernahme analog zu', 'typ' => 'user_select', 'required' => false, 'config' => [
                    'visible_when' => ['anrufuebernahme_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'bemerkungen', 'label' => 'Bemerkungen', 'typ' => 'textarea', 'required' => false],
            ]);

            $this->syncStepInputs($stepModels[3], [
                ['key' => 'add_ldap_groups', 'label' => 'LDAP-Gruppen hinzufügen', 'typ' => 'ldap_groups', 'required' => false],
                ['key' => 'remove_ldap_groups', 'label' => 'LDAP-Gruppen entfernen', 'typ' => 'ldap_groups', 'required' => false],
                ['key' => 'add_intranet_roles', 'label' => 'Intranet-Rollen hinzufügen', 'typ' => 'text', 'required' => false],
                ['key' => 'remove_intranet_roles', 'label' => 'Intranet-Rollen entfernen', 'typ' => 'text', 'required' => false],
                ['key' => 'pickup_uebernehmen', 'label' => 'Anrufübernahmegruppe übernehmen', 'typ' => 'ja_nein', 'required' => false, 'config' => [
                    'default' => '',
                    'visible_when' => ['anrufuebernahme_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'add_pickup_group', 'label' => 'Anrufübernahmegruppe', 'typ' => 'text', 'required' => false],
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
                ['key' => 'cms_check', 'label' => 'CMS erledigt', 'typ' => 'ja_nein', 'required' => true, 'config' => [
                    'default' => '',
                    'visible_when' => ['cms_benoetigt' => '1'],
                    'hidden_value' => '1',
                    'nein_label' => 'Nein (Ticket)',
                ]],
            ]);

            $resolve = WorkflowAction::query()->updateOrCreate(
                ['key' => 'core.resolve_assignee'],
                [
                    'title' => 'Workflow-Empfänger ermitteln',
                    'handler_key' => 'core.resolve_assignee',
                    'config' => ['fallback_to_initiator' => true],
                    'is_idempotent' => true,
                ],
            );

            $stepActions = [
                1 => [
                    ['key' => 'ma_umsetzung.capture_mitarbeiter_meta', 'title' => 'Mitarbeiter-Meta erfassen', 'handler' => 'ma_umsetzung.capture_mitarbeiter_meta'],
                ],
                2 => [
                    ['key' => 'ma_umsetzung.create_tickets_unterstuetzung', 'title' => 'Unterstützungstickets', 'handler' => 'ma_umsetzung.create_tickets_unterstuetzung'],
                ],
                3 => [
                    ['key' => 'ma_umsetzung.step3.placeholder', 'title' => 'Rechte-Auswahl gespeichert', 'handler' => 'demo.noop'],
                ],
                4 => [
                    ['key' => 'ma_umsetzung.remove_ldap_groups', 'title' => 'LDAP-Gruppen entfernen', 'handler' => 'ma_umsetzung.remove_ldap_groups', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.remove_intranet_roles', 'title' => 'Intranet-Rollen entfernen', 'handler' => 'ma_umsetzung.remove_intranet_roles', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.remove_bue_roles', 'title' => 'BuE-Rollen entfernen', 'handler' => 'ma_umsetzung.remove_bue_roles', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.remove_pickup_group', 'title' => 'Anrufübernahmegruppe entfernen', 'handler' => 'ma_umsetzung.remove_pickup_group', 'wait_for_due_date' => true],
                    ['key' => 'ma_neu.add_ldap_groups', 'title' => 'LDAP-Gruppen setzen', 'handler' => 'ma_neu.add_ldap_groups', 'wait_for_due_date' => true],
                    ['key' => 'ma_neu.add_intranet_roles', 'title' => 'Intranet-Rollen setzen', 'handler' => 'ma_neu.add_intranet_roles', 'wait_for_due_date' => true],
                    ['key' => 'ma_neu.add_bue_roles', 'title' => 'BuE-Rollen setzen', 'handler' => 'ma_neu.add_bue_roles', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.add_pickup_group', 'title' => 'Anrufübernahmegruppe setzen', 'handler' => 'ma_umsetzung.add_pickup_group', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.apply_cisco_standort', 'title' => 'Cisco Standort Handling', 'handler' => 'ma_umsetzung.apply_cisco_standort', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.intranet_user_update', 'title' => 'Intranet-User aktualisieren', 'handler' => 'ma_umsetzung.intranet_user_update', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.create_tickets', 'title' => 'Umsetzungstickets erstellen', 'handler' => 'ma_umsetzung.create_tickets'],
                    ['key' => 'ma_neu.initiator_mail', 'title' => 'Mail an Initiator', 'handler' => 'ma_neu.initiator_mail'],
                ],
            ];

            foreach ($stepActions as $position => $actions) {
                $step = $stepModels[$position];
                $desiredActionIds = [];

                WorkflowStepAction::query()
                    ->where('step_id', $step->id)
                    ->update(['position' => DB::raw('position + 1000')]);

                $pos = 1;
                foreach ($actions as $actionMeta) {
                    $action = WorkflowAction::query()->updateOrCreate(
                        ['key' => $actionMeta['key']],
                        [
                            'title' => $actionMeta['title'],
                            'handler_key' => $actionMeta['handler'],
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
                            'wait_for_due_date' => (bool) ($actionMeta['wait_for_due_date'] ?? false),
                            'config_override' => null,
                        ],
                    );
                    $desiredActionIds[] = $action->id;
                }

                $resolveConfig = match ($position) {
                    1 => [
                        'gvp_payload_key' => 'abteilung',
                        'fallback_to_initiator' => true,
                    ],
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
                        'wait_for_due_date' => false,
                        'config_override' => $resolveConfig,
                    ],
                );
                $desiredActionIds[] = $resolve->id;

                WorkflowStepAction::query()
                    ->where('step_id', $step->id)
                    ->whereNotIn('action_id', $desiredActionIds)
                    ->whereDoesntHave('actionRuns')
                    ->delete();
            }

            // noop action sicherstellen (Step 3 Platzhalter)
            WorkflowAction::query()->updateOrCreate(
                ['key' => 'demo.noop'],
                [
                    'title' => 'No-op (Platzhalter)',
                    'handler_key' => 'demo.noop',
                    'config' => ['label' => 'Placeholder'],
                    'is_idempotent' => true,
                ],
            );
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
