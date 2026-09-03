<?php

use function Livewire\Volt\{title};

title('Workflows - Bedienungsanleitung');

?>

<div>
    <x-intranet-app-workflows::workflows-layout heading="Bedienungsanleitung" subheading="Schritt-für-Schritt-Anleitung zur App">
        @livewire('intranet-app-base::manual-show', ['appIdentifier' => 'workflows'])
    </x-intranet-app-workflows::workflows-layout>
</div>
