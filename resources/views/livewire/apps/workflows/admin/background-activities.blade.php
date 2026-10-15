<?php

use function Livewire\Volt\title;

title('Hintergrund');

?>

<div>
    <x-intranet-app-workflows::workflows-layout heading="Hintergrund" subheading="Geplante Tasks, Jobs, Mails und Benachrichtigungen">
        @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'workflows'])
    </x-intranet-app-workflows::workflows-layout>
</div>
