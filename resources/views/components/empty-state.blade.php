@props(['icon' => 'inbox', 'title' => 'Sin resultados', 'description' => null])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="mb-3 grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
