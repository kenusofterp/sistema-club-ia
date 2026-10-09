@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    @include('partials.head', ['title' => $title ?? 'Portal del socio'])
</head>
<body class="min-h-full font-sans antialiased text-slate-800">
@php
    $portalNav = [
        ['route' => 'portal.dashboard', 'label' => 'Inicio', 'icon' => 'home'],
        ['route' => 'portal.fees', 'label' => 'Mi cuenta', 'icon' => 'banknotes'],
        ...(uses_gym() ? [['route' => 'portal.plans', 'label' => 'Mi plan', 'icon' => 'id-card']] : []),
        ['route' => 'portal.activities', 'label' => activity_label(true), 'icon' => 'trophy'],
        ['route' => 'portal.lessons', 'label' => 'Mis clases', 'icon' => 'clock'],
        ['route' => 'portal.tournaments', 'label' => 'Torneos', 'icon' => 'flag'],
        ['route' => 'portal.messages', 'label' => 'Mensajes', 'icon' => 'chat'],
        ['route' => 'portal.reservations', 'label' => 'Reservas', 'icon' => 'calendar'],
        ['route' => 'portal.card', 'label' => 'Carnet', 'icon' => 'qr'],
    ];
    // Móvil: 4 accesos principales + "Más" (estilo app).
    $mobileMain = collect($portalNav)->whereIn('route', ['portal.dashboard', 'portal.lessons', 'portal.fees', 'portal.card'])->values();
    $mobileMore = collect($portalNav)->whereNotIn('route', $mobileMain->pluck('route'))->push(['route' => 'portal.profile', 'label' => 'Mis datos', 'icon' => 'user'])->values();
@endphp

<header class="sticky top-0 z-30 bg-brand-900 text-white shadow">
    <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4">
        <a href="{{ route('portal.dashboard') }}" wire:navigate><x-logo light size="size-9" name-class="!text-base hidden sm:inline" /></a>
        <nav class="hidden items-center gap-1 md:flex">
            @foreach ($portalNav as $item)
                <a href="{{ route($item['route']) }}" wire:navigate
                   @class(['rounded-lg px-3 py-2 text-sm font-medium transition', 'bg-white/15 text-white' => request()->routeIs($item['route']), 'text-white/75 hover:bg-white/10 hover:text-white' => ! request()->routeIs($item['route'])])>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <button type="button" class="flex items-center gap-2 rounded-full p-1 hover:bg-white/10" x-on:click="open = !open">
                @if ($photo = auth()->user()->currentMember()?->photoUrl())
                    <img src="{{ $photo }}" alt="" class="size-8 rounded-full object-cover ring-2 ring-white/30">
                @else
                    <span class="grid size-8 place-items-center rounded-full bg-white/15 text-xs font-semibold">{{ auth()->user()->initials() }}</span>
                @endif
                <x-icon name="chevron-down" class="size-4 text-white/70" />
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 rounded-xl bg-white py-2 text-slate-700 shadow-lg ring-1 ring-slate-900/10">
                <div class="border-b border-slate-100 px-4 pb-2">
                    <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-500">Socio N° {{ auth()->user()->currentMember()?->member_number ?? '—' }}</p>
                </div>
                @php
                    $membershipOrgs = \App\Models\Organization::whereIn('id', auth()->user()->membershipOrganizationIds())->active()->get();
                @endphp
                @if ($membershipOrgs->count() > 1)
                    <p class="px-4 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Mis membresías</p>
                    @foreach ($membershipOrgs as $org)
                        <form method="POST" action="{{ route('portal.organization.switch') }}">
                            @csrf
                            <input type="hidden" name="organization_id" value="{{ $org->id }}">
                            <button type="submit" @class(['flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm hover:bg-slate-50', 'font-semibold text-brand-700' => $org->id === $currentOrganization?->id])>
                                <span class="truncate">{{ $org->name }}</span>
                                @if ($org->id === $currentOrganization?->id)<x-icon name="check" class="size-4 shrink-0" />@endif
                            </button>
                        </form>
                    @endforeach
                    <div class="my-1 border-t border-slate-100"></div>
                @endif
                <a href="{{ route('portal.profile') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="user" class="size-4" /> Mis datos</a>
                @if (auth()->user()->canAccessAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="dashboard" class="size-4" /> Administración</a>
                @endif
                <a href="{{ $currentOrganization?->url() ?? route('home') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="globe" class="size-4" /> Sitio del club</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</header>

<main class="mx-auto max-w-5xl px-4 pt-6 pb-28 md:pb-10">
    {{ $slot }}
</main>

{{-- Navegación inferior en móvil (estilo app) --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden" x-data="{ more: false }">
    <div class="grid grid-cols-5">
        @foreach ($mobileMain as $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               @class(['flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium', 'text-brand-700' => request()->routeIs($item['route']), 'text-slate-500' => ! request()->routeIs($item['route'])])>
                <x-icon :name="$item['icon']" class="size-6" />
                {{ $item['label'] }}
            </a>
        @endforeach
        <button type="button" x-on:click="more = true"
                @class(['flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium', 'text-brand-700' => request()->routeIs($mobileMore->pluck('route')->all()), 'text-slate-500' => ! request()->routeIs($mobileMore->pluck('route')->all())])>
            <x-icon name="menu" class="size-6" />
            Más
        </button>
    </div>

    {{-- Hoja inferior con el resto de las secciones --}}
    <div x-show="more" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="more = false">
        <div class="absolute inset-0 bg-slate-900/40" x-show="more" x-transition.opacity x-on:click="more = false"></div>
        <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-xl"
             x-show="more" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-200"></div>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($mobileMore as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate x-on:click="more = false"
                       class="flex flex-col items-center gap-1.5 rounded-xl p-3 text-center text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <x-icon :name="$item['icon']" class="size-6 text-brand-700" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</nav>

<x-toasts />
</body>
</html>
