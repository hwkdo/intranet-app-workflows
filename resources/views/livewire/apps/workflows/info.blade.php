<?php

use function Livewire\Volt\{title};

title('Workflows - App-Info');

?>

<div>
<x-intranet-app-workflows::workflows-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'workflows'])
</x-intranet-app-workflows::workflows-layout>
</div>
