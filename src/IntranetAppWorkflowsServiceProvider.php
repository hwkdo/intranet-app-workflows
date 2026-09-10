<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows;

use Hwkdo\IntranetAppWorkflows\Actions\ActionRegistry;
use Hwkdo\IntranetAppWorkflows\Commands\AzubiRotationCheckCommand;
use Hwkdo\IntranetAppWorkflows\Commands\AzubiRotationProcessCommand;
use Hwkdo\IntranetAppWorkflows\Commands\DumpLdapUserCommand;
use Hwkdo\IntranetAppWorkflows\Commands\ProcessWaitingActionRunsCommand;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOffboardGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenOnboardGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BitwardenSendGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\BueRolesGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoPickupGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\CiscoStandortGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeQuotaGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\ExchangeSharedMailboxGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Contracts\MailboxForwardingGatewayInterface;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\HostBitwardenOffboardGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\HostBitwardenOnboardGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\HostBitwardenSendGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\NullBitwardenOffboardGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\NullBitwardenOnboardGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bitwarden\NullBitwardenSendGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bue\HostBueRolesGateway;
use Hwkdo\IntranetAppWorkflows\Services\Bue\NullBueRolesGateway;
use Hwkdo\IntranetAppWorkflows\Services\Cisco\HostCiscoPickupGateway;
use Hwkdo\IntranetAppWorkflows\Services\Cisco\HostCiscoStandortGateway;
use Hwkdo\IntranetAppWorkflows\Services\Cisco\NullCiscoPickupGateway;
use Hwkdo\IntranetAppWorkflows\Services\Cisco\NullCiscoStandortGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\HostExchangeQuotaGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\HostExchangeSharedMailboxGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\HostMailboxForwardingGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\NullExchangeQuotaGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\NullExchangeSharedMailboxGateway;
use Hwkdo\IntranetAppWorkflows\Services\Exchange\NullMailboxForwardingGateway;
use Hwkdo\IntranetAppWorkflows\Services\Ldap\HostLdapIdentityGateway;
use Hwkdo\IntranetAppWorkflows\Services\Ldap\NullLdapIdentityGateway;
use Hwkdo\IntranetAppWorkflows\Services\WorkflowOrchestrator;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppWorkflowsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-workflows')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                DumpLdapUserCommand::class,
                ProcessWaitingActionRunsCommand::class,
                AzubiRotationCheckCommand::class,
                AzubiRotationProcessCommand::class,
            ])
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ActionRegistry::class, function (): ActionRegistry {
            $registry = new ActionRegistry;
            $registry->registerMany(config('intranet-app-workflows.actions', []));

            return $registry;
        });

        $this->app->singleton(WorkflowOrchestrator::class);

        $this->app->singleton(LdapIdentityGatewayInterface::class, function (): LdapIdentityGatewayInterface {
            if (class_exists(\App\Services\LdapRecordUserService::class)) {
                return new HostLdapIdentityGateway;
            }

            return new NullLdapIdentityGateway;
        });

        $this->app->singleton(BueRolesGatewayInterface::class, function (): BueRolesGatewayInterface {
            if (class_exists(\Hwkdo\BueLaravel\BueLaravel::class)) {
                return new HostBueRolesGateway;
            }

            return new NullBueRolesGateway;
        });

        $this->app->singleton(BitwardenSendGatewayInterface::class, function (): BitwardenSendGatewayInterface {
            if (class_exists(\Hwkdo\HwkAdminLaravel\HwkAdminService::class)) {
                return new HostBitwardenSendGateway;
            }

            return new NullBitwardenSendGateway;
        });

        $this->app->singleton(ExchangeQuotaGatewayInterface::class, function (): ExchangeQuotaGatewayInterface {
            if (class_exists(\Hwkdo\HwkAdminLaravel\HwkAdminService::class)) {
                return new HostExchangeQuotaGateway;
            }

            return new NullExchangeQuotaGateway;
        });

        $this->app->singleton(ExchangeSharedMailboxGatewayInterface::class, function (): ExchangeSharedMailboxGatewayInterface {
            if (class_exists(\Hwkdo\HwkAdminLaravel\HwkAdminService::class)) {
                return new HostExchangeSharedMailboxGateway;
            }

            return new NullExchangeSharedMailboxGateway;
        });

        $this->app->singleton(MailboxForwardingGatewayInterface::class, function (): MailboxForwardingGatewayInterface {
            if (class_exists(\Hwkdo\HwkAdminLaravel\HwkAdminService::class)) {
                return new HostMailboxForwardingGateway;
            }

            return new NullMailboxForwardingGateway;
        });

        $this->app->singleton(BitwardenOffboardGatewayInterface::class, function (): BitwardenOffboardGatewayInterface {
            if (interface_exists(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class)
                && $this->app->bound(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class)) {
                return new HostBitwardenOffboardGateway(
                    $this->app->make(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class),
                );
            }

            return new NullBitwardenOffboardGateway;
        });

        $this->app->singleton(BitwardenOnboardGatewayInterface::class, function (): BitwardenOnboardGatewayInterface {
            if (interface_exists(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class)
                && $this->app->bound(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class)) {
                return new HostBitwardenOnboardGateway(
                    $this->app->make(\Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface::class),
                );
            }

            return new NullBitwardenOnboardGateway;
        });

        $this->app->singleton(CiscoPickupGatewayInterface::class, function (): CiscoPickupGatewayInterface {
            if (interface_exists(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class)
                && $this->app->bound(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class)) {
                return new HostCiscoPickupGateway(
                    $this->app->make(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class),
                );
            }

            return new NullCiscoPickupGateway;
        });

        $this->app->singleton(CiscoStandortGatewayInterface::class, function (): CiscoStandortGatewayInterface {
            if (interface_exists(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class)
                && $this->app->bound(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class)) {
                return new HostCiscoStandortGateway(
                    $this->app->make(\Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface::class),
                );
            }

            return new NullCiscoStandortGateway;
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function (): void {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->resolving(Schedule::class, function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('workflows:process-waiting-actions')->everyFifteenMinutes();
            $schedule->command('workflows:azubi-rotation-check')->dailyAt('01:10');
        });
    }
}
