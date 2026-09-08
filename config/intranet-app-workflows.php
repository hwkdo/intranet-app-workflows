<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ActivateAdUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AddBueRolesAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AddIntranetRolesAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AddLdapGroupsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AddPickupGroupAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\AlwaysFailAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyArbeitskreiseAssignmentsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyAssetHabeIchErhaltenAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyAssetsDispositionsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyBeauftragungenAssignmentsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyCiscoStandortAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ApplyDokumenteAssignmentsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\BitwardenOffboardAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CaptureAustrittMitarbeiterMetaAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CaptureAustrittSupervisorMetaAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CaptureMitarbeiterMetaAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CiscoOffboardAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ClearPhoneFaxAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ConvertMailboxSharedAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CreateAdUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CreateTicketsMaNeuAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CreateTicketsMaUmsetzungAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\CreateTicketsUnterstuetzungAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\DeactivateAdUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\EnableRemoteMailboxAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ImportLdapUserAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\InitiatorMailAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\IntranetDeactivateAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\IntranetUserUpdateAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\MoveToAustrittOuAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\NoOpAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\OnboardingMailAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\RemoveAllLdapGroupsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\RemoveBueRolesAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\RemoveIntranetRolesAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\RemoveLdapGroupsAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\RemovePickupGroupAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\ResolveAssigneeAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\SendSupervisorPasswordBitwardenAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\SetMailboxForwardingAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\SetMailboxQuotaAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\SetPayloadMarkerAction;
use Hwkdo\IntranetAppWorkflows\Actions\Handlers\VorabMailAction;

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
        'hr' => [
            'name' => 'App-Workflows-Personalwesen',
            'permissions' => [
                'see-app-workflows',
                'all-app-workflows',
                'create-app-workflows',
            ],
        ],
        'user' => [
            'name' => 'App-Workflows-Benutzer',
            'permissions' => [
                'see-app-workflows',
            ],
            'all_users' => true,
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
        EnableRemoteMailboxAction::key() => EnableRemoteMailboxAction::class,
        SetMailboxQuotaAction::key() => SetMailboxQuotaAction::class,
        SendSupervisorPasswordBitwardenAction::key() => SendSupervisorPasswordBitwardenAction::class,
        ImportLdapUserAction::key() => ImportLdapUserAction::class,
        AddIntranetRolesAction::key() => AddIntranetRolesAction::class,
        AddBueRolesAction::key() => AddBueRolesAction::class,
        CreateTicketsMaNeuAction::key() => CreateTicketsMaNeuAction::class,
        OnboardingMailAction::key() => OnboardingMailAction::class,
        InitiatorMailAction::key() => InitiatorMailAction::class,
        CaptureMitarbeiterMetaAction::key() => CaptureMitarbeiterMetaAction::class,
        CreateTicketsUnterstuetzungAction::key() => CreateTicketsUnterstuetzungAction::class,
        CreateTicketsMaUmsetzungAction::key() => CreateTicketsMaUmsetzungAction::class,
        RemoveLdapGroupsAction::key() => RemoveLdapGroupsAction::class,
        RemoveIntranetRolesAction::key() => RemoveIntranetRolesAction::class,
        RemoveBueRolesAction::key() => RemoveBueRolesAction::class,
        AddPickupGroupAction::key() => AddPickupGroupAction::class,
        RemovePickupGroupAction::key() => RemovePickupGroupAction::class,
        ApplyCiscoStandortAction::key() => ApplyCiscoStandortAction::class,
        IntranetUserUpdateAction::key() => IntranetUserUpdateAction::class,

        // ma_austritt
        VorabMailAction::key() => VorabMailAction::class,
        CaptureAustrittMitarbeiterMetaAction::key() => CaptureAustrittMitarbeiterMetaAction::class,
        CaptureAustrittSupervisorMetaAction::key() => CaptureAustrittSupervisorMetaAction::class,
        ApplyAssetHabeIchErhaltenAction::key() => ApplyAssetHabeIchErhaltenAction::class,
        IntranetDeactivateAction::key() => IntranetDeactivateAction::class,
        ConvertMailboxSharedAction::key() => ConvertMailboxSharedAction::class,
        SetMailboxForwardingAction::key() => SetMailboxForwardingAction::class,
        ApplyDokumenteAssignmentsAction::key() => ApplyDokumenteAssignmentsAction::class,
        ApplyArbeitskreiseAssignmentsAction::key() => ApplyArbeitskreiseAssignmentsAction::class,
        ApplyBeauftragungenAssignmentsAction::key() => ApplyBeauftragungenAssignmentsAction::class,
        ApplyAssetsDispositionsAction::key() => ApplyAssetsDispositionsAction::class,
        RemoveAllLdapGroupsAction::key() => RemoveAllLdapGroupsAction::class,
        MoveToAustrittOuAction::key() => MoveToAustrittOuAction::class,
        ClearPhoneFaxAction::key() => ClearPhoneFaxAction::class,
        DeactivateAdUserAction::key() => DeactivateAdUserAction::class,
        BitwardenOffboardAction::key() => BitwardenOffboardAction::class,
        CiscoOffboardAction::key() => CiscoOffboardAction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | ma_austritt – LDAP / Austritt-OU
    |--------------------------------------------------------------------------
    */
    'austritt' => [
        'ou_dn' => env('WORKFLOWS_AUSTRITT_OU_DN', 'OU=Austritt,OU=hwkdo,DC=hwkdo,DC=local'),
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
    | require_username_prefix: optional; leer = keine Prüfung.
    */
    'phase_b' => [
        'enabled' => env('WORKFLOWS_PHASE_B_ENABLED', true),
        'dry_run' => env('WORKFLOWS_PHASE_B_DRY_RUN', true),
        'require_username_prefix' => env('WORKFLOWS_PHASE_B_USERNAME_PREFIX', ''),
        'upn_suffix' => env('WORKFLOWS_PHASE_B_UPN_SUFFIX', '@'.ltrim((string) env('MSGRAPH_DEFAULT_SUFFIX', 'hwk-do.de'), '@')),
        'homeshare_letter' => env('WORKFLOWS_PHASE_B_HOMESHARE_LETTER', 'P'),
        'bw_send_max_access_count' => (int) env('WORKFLOWS_PHASE_B_BW_SEND_MAX_ACCESS', 1),
        'bw_send_delete_in_days' => (int) env('WORKFLOWS_PHASE_B_BW_SEND_DELETE_DAYS', 7),
        'mail_absender' => env('WORKFLOWS_PHASE_B_MAIL_ABSENDER', env('WORKFLOWS_TICKET_ABSENDER', 'mailing@hwk-do.de')),
        'mail_absender_name' => env('WORKFLOWS_PHASE_B_MAIL_ABSENDER_NAME', env('WORKFLOWS_TICKET_ABSENDER_NAME', 'Intranet Workflows')),
        /*
        | Hybrid Remote Mailbox (Easy365Manager-Äquivalent per LDAP).
        | Wartet auf ExchangeLabs-x500 (Cloud-Mailbox nach Lizenz), dann Stamp.
        */
        'remote_mailbox' => [
            'poll_minutes' => (int) env('WORKFLOWS_REMOTE_MAILBOX_POLL_MINUTES', 15),
            'timeout_hours' => (int) env('WORKFLOWS_REMOTE_MAILBOX_TIMEOUT_HOURS', 24),
            'routing_domain' => env('WORKFLOWS_REMOTE_MAILBOX_ROUTING_DOMAIN', 'hwkdoedu.mail.onmicrosoft.com'),
            'onmicrosoft_domain' => env('WORKFLOWS_REMOTE_MAILBOX_ONMICROSOFT_DOMAIN', 'hwkdoedu.onmicrosoft.com'),
            'primary_mail_domain' => env('WORKFLOWS_REMOTE_MAILBOX_PRIMARY_DOMAIN', 'hwk-do.de'),
            'proxy_sam_domains' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'WORKFLOWS_REMOTE_MAILBOX_SAM_DOMAINS',
                    'hwk-do.de,hwkdo.de,verwaltung.hwkdo.de',
                )),
            ))),
            'ms_exch_recipient_display_type' => env('WORKFLOWS_REMOTE_MAILBOX_DISPLAY_TYPE', '-2147483642'),
            'ms_exch_recipient_type_details' => env('WORKFLOWS_REMOTE_MAILBOX_TYPE_DETAILS', '2147483648'),
            'ms_exch_remote_recipient_type' => env('WORKFLOWS_REMOTE_MAILBOX_REMOTE_TYPE', '4'),
            'ms_exch_version' => env('WORKFLOWS_REMOTE_MAILBOX_VERSION', '44220983382016'),
        ],
        /*
        | Exchange Online Mailbox Quotas (HwkAdmin exchange-quota-set).
        | Format wie Manager-UI: "4.9GB" / "5GB" / "4.5GB".
        | Wartet bis getExchangeQuota die Mailbox findet.
        */
        'mailbox_quota' => [
            'poll_minutes' => (int) env('WORKFLOWS_MAILBOX_QUOTA_POLL_MINUTES', 15),
            'timeout_hours' => (int) env('WORKFLOWS_MAILBOX_QUOTA_TIMEOUT_HOURS', 24),
            'prohibit_send' => env('WORKFLOWS_MAILBOX_QUOTA_PROHIBIT_SEND', '4.9GB'),
            'prohibit_send_receive' => env('WORKFLOWS_MAILBOX_QUOTA_PROHIBIT_SEND_RECEIVE', '5GB'),
            'issue_warning' => env('WORKFLOWS_MAILBOX_QUOTA_ISSUE_WARNING', '4.5GB'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase C – Rechte / Tickets (Intranet-Rollen, BuE-Rollen, Ticket-Mails)
    |--------------------------------------------------------------------------
    | dry_run=true: Erfolg ohne Mailversand / ohne Rollen-Schreiben.
    */
    'phase_c' => [
        'enabled' => env('WORKFLOWS_PHASE_C_ENABLED', true),
        'dry_run' => env('WORKFLOWS_PHASE_C_DRY_RUN', true),
        'ticket_perseus_bei_ma_neu' => env('WORKFLOWS_TICKET_PERSEUS', true),
        'ticket_absender' => env('WORKFLOWS_TICKET_ABSENDER', 'mailing@hwk-do.de'),
        'ticket_absender_name' => env('WORKFLOWS_TICKET_ABSENDER_NAME', 'Intranet Workflows'),
        'ticket_recipients' => [
            'it_verwaltung' => env('WORKFLOWS_TICKET_IT_VERWALTUNG', 'it_verwaltung@hwkdo.de'),
            'it_schulung' => env('WORKFLOWS_TICKET_IT_SCHULUNG', 'ticket@hwkdo.de'),
            'dms' => env('WORKFLOWS_TICKET_DMS', 'dms@hwkdo.de'),
            'perso' => env('WORKFLOWS_TICKET_PERSO', 'petra.wessel@hwk-do.de'),
            'haustechnik' => env('WORKFLOWS_TICKET_HAUSTECHNIK', 'haustechnik@hwkdo.de'),
            'arbeitsschutz' => env('WORKFLOWS_TICKET_ARBEITSSCHUTZ', 'arbeitsschutz@hwk-do.de'),
        ],
        'default_intranet_roles' => [
            'Benutzer',
        ],
        'protected_intranet_roles' => [
            'App-Workflows-Admin',
            'Personalwesen-Workflows',
            'Workflows-IT',
            'Workflows-HR',
        ],
        'default_bue_roles' => [
            'NUTZER_BASE',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase D – Onboarding-/Initiator-Mails
    |--------------------------------------------------------------------------
    | dry_run=true: Erfolg ohne Mailversand.
    | Onboarding-Link: Route-Name (wenn registriert) oder URL-Template mit {id}/{username}.
    */
    'phase_d' => [
        'enabled' => env('WORKFLOWS_PHASE_D_ENABLED', true),
        'dry_run' => env('WORKFLOWS_PHASE_D_DRY_RUN', true),
        'onboarding_subject' => env('WORKFLOWS_ONBOARDING_SUBJECT', 'Onboarding Dokumente!'),
        'onboarding_route' => env('WORKFLOWS_ONBOARDING_ROUTE', 'apps.formwerk.onboarding'),
        'onboarding_url_template' => env('WORKFLOWS_ONBOARDING_URL_TEMPLATE'),
        'mail_absender' => env('WORKFLOWS_PHASE_D_ABSENDER', env('WORKFLOWS_TICKET_ABSENDER', 'mailing@hwk-do.de')),
        'mail_absender_name' => env('WORKFLOWS_PHASE_D_ABSENDER_NAME', env('WORKFLOWS_TICKET_ABSENDER_NAME', 'Intranet Workflows')),
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

    /*
    |--------------------------------------------------------------------------
    | Azubi-Rotation (auto ma_umsetzung aus AzubiEinsatz)
    |--------------------------------------------------------------------------
    */
    'azubi_rotation' => [
        'initiator_username' => env('WORKFLOWS_AZUBI_ROTATION_INITIATOR', 'hwkdo454'),
        'bemerkungen' => 'Automatische Azubirotation',
        'source' => 'azubi_rotation',
    ],
];
