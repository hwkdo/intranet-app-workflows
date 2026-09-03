<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesManualsInterface;
use Hwkdo\IntranetAppWorkflows\Data\AppSettings;
use Hwkdo\IntranetAppWorkflows\Data\UserSettings;
use Illuminate\Support\Collection;

class IntranetAppWorkflows implements IntranetAppInterface, ProvidesManualsInterface
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
     * @return list<ManualDefinition>
     */
    public static function manuals(): array
    {
        return [];
    }
}
