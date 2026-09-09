<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Workflows');

?>

<div>
    <x-intranet-app-workflows::workflows-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für Workflows">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'workflows'])
    </x-intranet-app-workflows::workflows-layout>
</div>
