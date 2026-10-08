{{--
    Modal controlado por una propiedad booleana de Livewire: <x-modal wire:model="showForm" title="...">
--}}
@props(['title' => null, 'maxWidth' => 'max-w-2xl'])
<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="open = false"></div>
    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative w-full {{ $maxWidth }} rounded-2xl bg-white shadow-xl"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                    <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" x-on:click="open = false">
                        <x-icon name="x" />
                        <span class="sr-only">Cerrar</span>
                    </button>
                </div>
            @endif
            <div class="px-6 py-5">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="flex flex-col-reverse gap-2 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
