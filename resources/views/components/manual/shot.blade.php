@props(['src', 'caption' => null])
{{-- Captura de pantalla del manual de uso (public/images/manual). Clic para ampliar. --}}
<figure class="my-5" x-data="{ zoom: false }">
    <button type="button" x-on:click="zoom = true" class="block w-full cursor-zoom-in overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm transition hover:shadow-md">
        <img src="{{ asset('images/manual/'.$src) }}" alt="{{ $caption }}" loading="lazy" class="w-full">
    </button>
    @if ($caption)
        <figcaption class="mt-2 text-center text-xs text-slate-500">{{ $caption }}</figcaption>
    @endif
    <template x-teleport="body">
        <div x-show="zoom" x-cloak x-transition.opacity x-on:click="zoom = false" x-on:keydown.escape.window="zoom = false"
             class="fixed inset-0 z-[70] flex cursor-zoom-out items-center justify-center bg-slate-900/80 p-4">
            <img src="{{ asset('images/manual/'.$src) }}" alt="{{ $caption }}" class="max-h-full max-w-full rounded-lg shadow-2xl">
        </div>
    </template>
</figure>
