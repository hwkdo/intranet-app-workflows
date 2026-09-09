@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.workflows.index'), 'icon' => 'queue-list', 'description' => 'Alle sichtbaren Workflows', 'buttonText' => 'Übersicht öffnen'],
    ];

    if (\Hwkdo\IntranetAppWorkflows\Support\FlowAccess::canCreate(auth()->user())) {
        $defaultNavItems[] = [
            'label' => 'Neuer Workflow',
            'href' => route('apps.workflows.flows.new'),
            'icon' => 'plus-circle',
            'description' => 'Neuen Workflow starten',
            'buttonText' => 'Typ wählen',
        ];
    }

    $defaultNavItems = array_merge($defaultNavItems, [
        ['label' => 'App-Info', 'href' => route('apps.workflows.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.workflows.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-workflows'],
    ]);

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
