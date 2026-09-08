<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesManualsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppWorkflows\Data\AppSettings;
use Hwkdo\IntranetAppWorkflows\Data\UserSettings;
use Hwkdo\IntranetAppWorkflows\Tasks\AssigneePendingTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppWorkflows implements IntranetAppInterface, ProvidesManualsInterface, ProvidesNotificationsInterface, ProvidesTasksInterface
{
    public static function app_name(): string
    {
        return 'Workflows';
    }

    public static function app_icon(): string
    {
        return 'queue-list';
    }

    public static function identifier(): string
    {
        return 'workflows';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-workflows.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-workflows.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    /**
     * @return array<class-string<TaskProviderInterface>>
     */
    public static function taskProviders(): array
    {
        return [
            AssigneePendingTaskProvider::class,
        ];
    }

    /**
     * @return list<NotificationTypeDefinition>
     */
    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'workflows.assigned_personal',
                label: 'Workflow zugewiesen (persönlich)',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Benachrichtigung, wenn dir ein Workflow-Schritt persönlich zugewiesen wurde.',
                mandatory: true,
                defaultEnabled: true,
                defaultChannels: ['inbox', 'mail'],
            ),
            new NotificationTypeDefinition(
                key: 'workflows.assigned_group',
                label: 'Workflow zugewiesen (Gruppe)',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Benachrichtigung, wenn ein Workflow in einer deiner Empfängergruppen zur Übernahme bereitliegt.',
                mandatory: false,
                defaultEnabled: true,
            ),
        ];
    }

    /**
     * @return list<ManualDefinition>
     */
    public static function manuals(): array
    {
        return [];
    }
}
