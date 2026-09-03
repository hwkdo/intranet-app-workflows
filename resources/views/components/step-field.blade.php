@props([
    'input',
    'wireModel' => 'form',
    'live' => false,
])

@php
    $key = $input->key;
    $model = $wireModel.'.'.$key;
    $required = (bool) ($input->pivot->required ?? false)
        || (bool) (($input->config['required_when_visible'] ?? false));
    $config = $input->config ?? [];
    $options = $config['options'] ?? [];
@endphp

<flux:field>
    <flux:label>{{ $input->label }}@if($required) *@endif</flux:label>
    @if($input->infotext)
        <flux:description>{{ $input->infotext }}</flux:description>
    @endif

    @switch($input->typ)
        @case('textarea')
            @if($live)
                <flux:textarea wire:model.live="{{ $model }}" rows="3" />
            @else
                <flux:textarea wire:model="{{ $model }}" rows="3" />
            @endif
            @break

        @case('date')
            @if($live)
                <flux:input type="date" wire:model.live="{{ $model }}" />
            @else
                <flux:input type="date" wire:model="{{ $model }}" />
            @endif
            @break

        @case('ja_nein')
            @if($live)
                <flux:select wire:model.live="{{ $model }}" placeholder="Bitte wählen…" clearable>
                    <flux:select.option value="1">Ja</flux:select.option>
                    <flux:select.option value="0">Nein</flux:select.option>
                </flux:select>
            @else
                <flux:select wire:model="{{ $model }}" placeholder="Bitte wählen…" clearable>
                    <flux:select.option value="1">Ja</flux:select.option>
                    <flux:select.option value="0">Nein</flux:select.option>
                </flux:select>
            @endif
            @break

        @case('single_select')
            @if($live)
                <flux:select wire:model.live="{{ $model }}" placeholder="Bitte wählen…">
                    @foreach($options as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="{{ $model }}" placeholder="Bitte wählen…">
                    @foreach($options as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @break

        @case('gvp_select')
            @if($live)
                <flux:select wire:model.live="{{ $model }}" placeholder="Abteilung wählen…" variant="listbox" searchable>
                    @foreach(\App\Models\Gvp::query()->orderBy('name')->get() as $gvp)
                        <flux:select.option value="{{ $gvp->id }}">{{ $gvp->bezeichnung }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="{{ $model }}" placeholder="Abteilung wählen…" variant="listbox" searchable>
                    @foreach(\App\Models\Gvp::query()->orderBy('name')->get() as $gvp)
                        <flux:select.option value="{{ $gvp->id }}">{{ $gvp->bezeichnung }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @break

        @case('standort_select')
            @if($live)
                <flux:select wire:model.live="{{ $model }}" placeholder="Standort wählen…" variant="listbox" searchable>
                    @foreach(\App\Models\Standort::query()->orderBy('name')->get() as $standort)
                        <flux:select.option value="{{ $standort->id }}">{{ $standort->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="{{ $model }}" placeholder="Standort wählen…" variant="listbox" searchable>
                    @foreach(\App\Models\Standort::query()->orderBy('name')->get() as $standort)
                        <flux:select.option value="{{ $standort->id }}">{{ $standort->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @break

        @case('user_select')
            @if($live)
                <flux:select wire:model.live="{{ $model }}" placeholder="Mitarbeiter wählen…" variant="listbox" searchable clearable>
                    @foreach(\Hwkdo\IntranetAppWorkflows\Support\WorkflowModels::activeUsersForSelect() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="{{ $model }}" placeholder="Mitarbeiter wählen…" variant="listbox" searchable clearable>
                    @foreach(\Hwkdo\IntranetAppWorkflows\Support\WorkflowModels::activeUsersForSelect() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @break

        @default
            @if($live)
                <flux:input wire:model.live="{{ $model }}" />
            @else
                <flux:input wire:model="{{ $model }}" />
            @endif
    @endswitch

    <flux:error name="{{ $wireModel.'.'.$key }}" />
</flux:field>
