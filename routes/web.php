<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'can:see-app-workflows'])->group(function () {
    Volt::route('apps/workflows', 'apps.workflows.index')->name('apps.workflows.index');
    Volt::route('apps/workflows/flows/new', 'apps.workflows.flows.new')->name('apps.workflows.flows.new');
    Volt::route('apps/workflows/flows/create/{typeKey?}', 'apps.workflows.flows.create')
        ->name('apps.workflows.flows.create')
        ->where('typeKey', 'ma_neu|ma_umsetzung|ma_austritt');
    Volt::route('apps/workflows/flows/{flow}', 'apps.workflows.flows.show')->name('apps.workflows.flows.show');
    Volt::route('apps/workflows/example', 'apps.workflows.example')->name('apps.workflows.example');
    Volt::route('apps/workflows/info', 'apps.workflows.info')->name('apps.workflows.info');
    Volt::route('apps/workflows/manual', 'apps.workflows.manual')->name('apps.workflows.manual');
});

Route::middleware(['web', 'auth', 'can:manage-app-workflows'])->group(function () {
    Volt::route('apps/workflows/admin', 'apps.workflows.admin.index')->name('apps.workflows.admin.index');
});
