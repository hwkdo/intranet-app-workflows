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
 * ma_austritt: HR → Vorgesetzter → IT-Checkliste (+ Stichtag-Actions).
 */
final class MaAustrittWorkflowSeeder
{
    public function seed(): void
    {
        AssigneeGroups::ensureRolesExist();

        DB::transaction(function (): void {
            $type = WorkflowType::query()->updateOrCreate(
                ['key' => 'ma_austritt'],
                [
                    'title' => 'Mitarbeiter Austritt',
                    'description' => 'Offboarding: Shared Mailbox, Weiterleitung, Domain-Übergaben, AD/Intranet deaktivieren.',
                    'is_active' => true,
                ],
            );

            $steps = [
                1 => ['key' => 'hr_init', 'title' => 'Initialisierung – HR'],
                2 => ['key' => 'vorgesetzter', 'title' => 'Vorgesetzter'],
                3 => ['key' => 'it_checkliste', 'title' => 'IT – Checkliste'],
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
                ['key' => 'austrittsdatum', 'label' => 'Austrittsdatum (letzter Arbeitstag)', 'typ' => 'date', 'required' => true, 'infotext' => 'Stichtag der Automatik = Austrittsdatum + 1 Tag'],
                ['key' => 'bemerkungen', 'label' => 'Bemerkungen', 'typ' => 'textarea', 'required' => false],
            ]);

            $this->syncStepInputs($stepModels[2], [
                ['key' => 'email_weiterleitung_benoetigt', 'label' => 'E-Mail-Weiterleitung einrichten', 'typ' => 'ja_nein', 'required' => true],
                ['key' => 'email_weiterleitung_an', 'label' => 'Weiterleitung an', 'typ' => 'user_select', 'required' => false, 'config' => [
                    'visible_when' => ['email_weiterleitung_benoetigt' => '1'],
                    'required_when_visible' => true,
                ]],
                ['key' => 'bemerkungen_vg', 'label' => 'Bemerkungen', 'typ' => 'textarea', 'required' => false],
            ]);

            $this->syncStepInputs($stepModels[3], [
                ['key' => 'it_bemerkung', 'label' => 'IT-Bemerkung (optional)', 'typ' => 'textarea', 'required' => false],
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
                    ['key' => 'ma_austritt.capture_mitarbeiter_meta', 'title' => 'Mitarbeiter-Meta erfassen', 'handler' => 'ma_austritt.capture_mitarbeiter_meta'],
                    ['key' => 'ma_austritt.vorab_mail', 'title' => 'Vorab-Mail', 'handler' => 'ma_austritt.vorab_mail'],
                ],
                2 => [
                    ['key' => 'ma_austritt.capture_supervisor_meta', 'title' => 'Vorgesetzten-Meta erfassen', 'handler' => 'ma_austritt.capture_supervisor_meta'],
                    ['key' => 'ma_austritt.apply_asset_habe_ich_erhalten', 'title' => 'Assets: habe ich erhalten', 'handler' => 'ma_austritt.apply_asset_habe_ich_erhalten'],
                ],
                3 => [
                    ['key' => 'ma_austritt.intranet_deactivate', 'title' => 'Intranet deaktivieren', 'handler' => 'ma_austritt.intranet_deactivate', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.convert_mailbox_shared', 'title' => 'Mailbox → Shared', 'handler' => 'ma_austritt.convert_mailbox_shared', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.set_mailbox_forwarding', 'title' => 'E-Mail-Weiterleitung', 'handler' => 'ma_austritt.set_mailbox_forwarding', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.apply_dokumente_assignments', 'title' => 'Dokumente übertragen', 'handler' => 'ma_austritt.apply_dokumente_assignments', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.apply_arbeitskreise_assignments', 'title' => 'Arbeitskreise übertragen', 'handler' => 'ma_austritt.apply_arbeitskreise_assignments', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.apply_beauftragungen_assignments', 'title' => 'Beauftragungen übertragen', 'handler' => 'ma_austritt.apply_beauftragungen_assignments', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.apply_assets_dispositions', 'title' => 'Asset-Dispositionen', 'handler' => 'ma_austritt.apply_assets_dispositions', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.remove_all_ldap_groups', 'title' => 'LDAP-Gruppen entfernen', 'handler' => 'ma_austritt.remove_all_ldap_groups', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.move_to_austritt_ou', 'title' => 'OU Austritt', 'handler' => 'ma_austritt.move_to_austritt_ou', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.clear_phone_fax', 'title' => 'Telefon/Fax leeren', 'handler' => 'ma_austritt.clear_phone_fax', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.deactivate_ad_user', 'title' => 'AD deaktivieren', 'handler' => 'ma_austritt.deactivate_ad_user', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.bitwarden_offboard', 'title' => 'Bitwarden Offboard', 'handler' => 'ma_austritt.bitwarden_offboard', 'wait_for_due_date' => true],
                    ['key' => 'ma_austritt.cisco_offboard', 'title' => 'Cisco Offboard', 'handler' => 'ma_austritt.cisco_offboard', 'wait_for_due_date' => true],
                    ['key' => 'ma_umsetzung.remove_pickup_group', 'title' => 'Pickup entfernen', 'handler' => 'ma_umsetzung.remove_pickup_group', 'wait_for_due_date' => true],
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
                        'gvp_from_mitarbeiter' => true,
                        'fallback_to_initiator' => true,
                    ],
                    2 => [
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
