@props(['status' => null, 'color' => null])
@php
    if ($status instanceof \BackedEnum && method_exists($status, 'badgeClasses')) {
        $classes = $status->badgeClasses();
        $label = $status->label();
    } else {
        $classes = match ($color) {
            'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'yellow' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
            'red' => 'bg-red-50 text-red-700 ring-red-600/20',
            'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
            default => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
        $label = null;
    }
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$classes]) }}>{{ $label ?? $slot }}</span>
