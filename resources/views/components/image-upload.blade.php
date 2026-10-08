{{-- Selector de imagen con vista previa para Livewire (WithFileUploads). --}}
@props(['model', 'file' => null, 'current' => null, 'label' => 'Imagen', 'help' => null, 'aspect' => 'aspect-video', 'removable' => null])
@php
    $preview = $file && method_exists($file, 'temporaryUrl') ? rescue(fn () => $file->temporaryUrl(), null, false) : $current;
@endphp
<div>
    <span class="form-label">{{ $label }}</span>
    <div class="flex items-start gap-4">
        <div class="{{ $aspect }} w-40 shrink-0 overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50">
            @if ($preview)
                <img src="{{ $preview }}" alt="" class="size-full object-cover">
            @else
                <div class="grid size-full place-items-center text-slate-400"><x-icon name="photo" class="size-8" /></div>
            @endif
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <label class="btn-secondary btn-sm cursor-pointer">
                <x-icon name="photo" class="size-4" /> Elegir archivo
                <input type="file" wire:model="{{ $model }}" accept="image/*" class="sr-only">
            </label>
            <div wire:loading wire:target="{{ $model }}" class="text-xs text-slate-500">Subiendo…</div>
            @if ($removable && $current)
                <button type="button" wire:click="{{ $removable }}" class="block text-xs font-medium text-red-600 hover:underline">Quitar imagen</button>
            @endif
            @if ($help)
                <p class="form-help">{{ $help }}</p>
            @endif
            @error($model)
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
