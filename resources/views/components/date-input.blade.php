{{--
    Selector de fecha (o fecha y hora con :time="true") en formato dd/mm/aaaa, independiente del idioma del navegador.
    Se usa como un input: <x-date-input id="x" wire:model="fecha" :min="..." :max="..." />.
    El valor que recibe Livewire sigue siendo Y-m-d (o Y-m-d\TH:i con hora), igual que un input nativo.
--}}
@props(['time' => false, 'min' => null, 'max' => null])
<div x-data="dateInput({ time: @js((bool) $time) })" x-modelable="value" {{ $attributes->wire('model') }}
     data-min="{{ $min }}" data-max="{{ $max }}" class="contents">
    <div wire:ignore class="contents">
        <input type="text" x-ref="input" @unless ($time) inputmode="numeric" @endunless autocomplete="off"
               placeholder="{{ $time ? 'dd/mm/aaaa hh:mm' : 'dd/mm/aaaa' }}"
               {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'form-input']) }}>
    </div>
</div>
