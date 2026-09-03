@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.workflows.index'), 'icon' => 'queue-list', 'description' => 'Alle sichtbaren Workflows', 'buttonText' => 'Übersicht öffnen'],
        ['label' => 'Neueinstellung', 'href' => route('apps.workflows.flows.create'), 'icon' => 'user-plus', 'description' => 'Mitarbeiter-Neueinstellung starten', 'buttonText' => 'Starten'],
        ['label' => 'Meine Einstellungen', 'href' => route('apps.workflows.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'Bedienungsanleitung', 'href' => route('apps.workflows.manual'), 'icon' => 'book-open', 'description' => 'Ausführliche Anleitung zur App', 'buttonText' => 'Anleitung öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.workflows.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.workflows.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-workflows']
    ];

    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('workflows');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

<x-intranet-app-base::app-layout
    app-identifier="workflows"
    :heading="$heading"
    :subheading="$subheading"
    :nav-items="$navItems"
>
    {{ $slot }}
</x-intranet-app-base::app-layout>
