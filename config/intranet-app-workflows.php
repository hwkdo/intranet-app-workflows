<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ActivateAdUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AddLdapGroupsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AlwaysFailAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CreateAdUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ImportLdapUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\NoOpAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ResolveAssigneeAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\SetPayloadMarkerAction;

return [
    'user_model' => env('WORKFLOWS_USER_MODEL', User::class),

    'roles' => [
        'admin' => [
            'name' => 'App-Workflows-Admin',
            'permissions' => [
                'see-app-workflows',
                'manage-app-workflows',
            ],
        ],
        'user' => [
            'name' => 'App-Workflows-Benutzer',
            'permissions' => [
                'see-app-workflows',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action handlers (key => class)
    |--------------------------------------------------------------------------
    */
    'actions' => [
        NoOpAction::key() => NoOpAction::class,
        SetPayloadMarkerAction::key() => SetPayloadMarkerAction::class,
        AlwaysFailAction::key() => AlwaysFailAction::class,
        ResolveAssigneeAction::key() => ResolveAssigneeAction::class,
        CreateAdUserAction::key() => CreateAdUserAction::class,
        AddLdapGroupsAction::key() => AddLdapGroupsAction::class,
        ActivateAdUserAction::key() => ActivateAdUserAction::class,
        ImportLdapUserAction::key() => ImportLdapUserAction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Empfängergruppen (Legacy GroupReceiver)
    |--------------------------------------------------------------------------
    */
    'assignee_groups' => [
        'it' => [
            'role' => 'Workflows-IT',
            'label' => 'IT',
        ],
        'hr' => [
            'role' => 'Workflows-HR',
            'label' => 'Personalwesen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase B – Identity (AD / LDAP / Intranet-Import)
    |--------------------------------------------------------------------------
    | dry_run=true: Erfolg ohne LDAP-Schreibzugriff (sicher zum Durchklicken).
    | require_username_prefix: leer = keine Prüfung; sonst z. B. "testwf.".
    */
    'phase_b' => [
        'enabled' => env('WORKFLOWS_PHASE_B_ENABLED', true),
        'dry_run' => env('WORKFLOWS_PHASE_B_DRY_RUN', true),
        'require_username_prefix' => env('WORKFLOWS_PHASE_B_USERNAME_PREFIX', 'testwf.'),
        'upn_suffix' => env('WORKFLOWS_PHASE_B_UPN_SUFFIX', '@'.ltrim((string) env('MSGRAPH_DEFAULT_SUFFIX', 'hwk-do.de'), '@')),
        'homeshare_letter' => env('WORKFLOWS_PHASE_B_HOMESHARE_LETTER', 'P'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP / AD defaults for ma_neu Step 3 (Forced Groups)
    |--------------------------------------------------------------------------
    */
    'ldap' => [
        'farbdruck_group' => env('WORKFLOWS_LDAP_FARBDRUCK_GROUP', 'DG_TA-Farbdruck'),
        'gvp_adgroups_dn' => env('WORKFLOWS_GVP_ADGROUPS_DN', 'OU=GVP,OU=Groups,DC=hwkdo,DC=local'),
        'default_user_groups' => [
            'DG_intranet_user',
            'DG_RE_Workflow_Alle',
            'DG_share_universl-hwk',
            'DG_EV_Mitarbeiter',
        ],
        'default_dozenten_groups' => [
            'DG_d3_Dozenten',
        ],
        'default_nicht_dozenten' => [
            // Legacy: leer
        ],
        'default_edulicense_true' => [
            'adm_glb_azure_lizenz_education_e3',
            'adm_glb_azure_lizenz_education_e3_exchange',
        ],
        'default_edulicense_false' => [
            'adm_glb_azure_lizenz_commercial_e3',
            'adm_glb_azure_lizenz_commercial_e3_exchange',
        ],
        'default_azubis' => [
            'DG_EV_Azubis',
            'DG_share_AZUBI_HWK_DO',
        ],
    ],
];
