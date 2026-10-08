@props(['label', 'value', 'icon' => 'chart', 'hint' => null, 'href' => null, 'tone' => 'brand'])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700',
        'red' => 'bg-red-50 text-red-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'sky' => 'bg-sky-50 text-sky-600',
    ];
@endphp
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif class="card flex items-start gap-4 p-5 {{ $href ? 'transition hover:border-brand-300 hover:shadow' : '' }}">
    <div class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <div class="min-w-0">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="mt-0.5 truncate font-display text-2xl font-bold tabular-nums text-slate-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</{{ $href ? 'a' : 'div' }}>
