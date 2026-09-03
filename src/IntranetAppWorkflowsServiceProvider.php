<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows;

use Hwkdo\IntranetAppWorkflows\Actions\ActionRegistry;
use Hwkdo\IntranetAppWorkflows\Commands\ProcessWaitingActionRunsCommand;
use Hwkdo\IntranetAppWorkflows\Contracts\LdapIdentityGatewayInterface;
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
                ProcessWaitingActionRunsCommand::class,
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
            $schedule->command('workflows:process-waiting-actions')->dailyAt('03:00');
        });
    }
}
