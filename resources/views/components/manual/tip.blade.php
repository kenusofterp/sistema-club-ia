@props(['type' => 'tip', 'title' => null])
@php
    [$classes, $icon, $default] = match ($type) {
        'example' => ['border-amber-200 bg-amber-50 text-amber-900', 'sparkles', 'Ejemplo'],
        'warning' => ['border-red-200 bg-red-50 text-red-900', 'warning', 'Importante'],
        default => ['border-sky-200 bg-sky-50 text-sky-900', 'info', 'Consejo'],
    };
@endphp
<div class="my-4 rounded-xl border p-4 text-sm {{ $classes }}">
    <p class="mb-1 flex items-center gap-2 font-semibold"><x-icon :name="$icon" class="size-4" /> {{ $title ?? $default }}</p>
    <div class="space-y-2 leading-relaxed">{{ $slot }}</div>
</div>
