@props(['light' => false, 'size' => 'size-10', 'withName' => true, 'nameClass' => ''])
@php
    $logoLight = $light ? \App\Models\Setting::imageUrl('site.logo_light') : null;
    $logo = $logoLight ?? \App\Models\Setting::imageUrl('site.logo');
    // Sobre fondo oscuro sin versión clara cargada, el logo va en un recuadro blanco para que no pierda contraste.
    $tile = $light && $logo && ! $logoLight;
    $name = setting('site.name', config('app.name'));
    $short = setting('site.short_name') ?: collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(3)->implode('');
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    @if ($logo)
        @if ($tile)
            <span class="{{ $size }} grid shrink-0 place-items-center rounded-xl bg-white p-1 shadow-sm">
                <img src="{{ $logo }}" alt="{{ $name }}" class="size-full object-contain">
            </span>
        @else
            <img src="{{ $logo }}" alt="{{ $name }}" class="{{ $size }} shrink-0 object-contain">
        @endif
    @else
        <span class="{{ $size }} grid shrink-0 place-items-center rounded-xl {{ $light ? 'bg-white/15 text-white ring-1 ring-white/30' : 'bg-brand-600 text-white' }} font-display text-sm font-bold tracking-tight">{{ mb_strtoupper(mb_substr($short, 0, 3)) }}</span>
    @endif
    @if ($withName)
        <span class="font-display text-lg leading-tight font-bold {{ $light ? 'text-white' : 'text-slate-900' }} {{ $nameClass }}">{{ $name }}</span>
    @endif
</span>
