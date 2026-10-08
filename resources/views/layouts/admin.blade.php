@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body class="h-full font-sans antialiased text-slate-800" x-data="{ sidebar: false }">
@php
    $menu = \App\Support\AdminMenu::for(auth()->user());
@endphp

{{-- Sidebar móvil --}}
<div x-show="sidebar" x-cloak class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
    <div x-show="sidebar" x-transition.opacity class="fixed inset-0 bg-slate-900/60" x-on:click="sidebar = false"></div>
    <div class="fixed inset-0 flex">
        <div x-show="sidebar" x-transition:enter="transition ease-in-out duration-200 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="relative mr-16 flex w-full max-w-xs flex-1">
            <button type="button" class="absolute top-4 left-full ml-3 text-white" x-on:click="sidebar = false"><x-icon name="x" class="size-6" /><span class="sr-only">Cerrar menú</span></button>
            @include('layouts.partials.admin-sidebar', ['menu' => $menu])
        </div>
    </div>
</div>

{{-- Sidebar escritorio --}}
<div class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-72 lg:flex-col">
    @include('layouts.partials.admin-sidebar', ['menu' => $menu])
</div>

<div class="lg:pl-72">
    <header class="sticky top-0 z-30 flex h-16 items-center gap-x-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
        <button type="button" class="-m-2.5 p-2.5 text-slate-600 lg:hidden" x-on:click="sidebar = true">
            <x-icon name="menu" class="size-6" /><span class="sr-only">Abrir menú</span>
        </button>
        <div class="flex flex-1 items-center justify-between gap-4">
            @php
                $adminOrgIds = auth()->user()->adminOrganizationIds();
                $adminOrgs = count($adminOrgIds) > 1 ? \App\Models\Organization::whereIn('id', $adminOrgIds)->active()->get() : collect();
            @endphp
            <div class="flex min-w-0 items-center gap-3">
                @if ($adminOrgs->isNotEmpty())
                    {{-- Selector de entidad (club / gimnasio) --}}
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" x-on:click="open = !open" class="flex max-w-64 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                            <x-icon :name="$currentOrganization?->type === 'gimnasio' ? 'trophy' : 'building'" class="size-4 shrink-0 text-brand-600" />
                            <span class="truncate">{{ $currentOrganization?->name }}</span>
                            <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-400" />
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute left-0 z-40 mt-2 w-72 rounded-xl bg-white py-2 shadow-lg ring-1 ring-slate-900/10">
                            <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Cambiar de entidad</p>
                            @foreach ($adminOrgs as $org)
                                <form method="POST" action="{{ route('admin.organization.switch') }}">
                                    @csrf
                                    <input type="hidden" name="organization_id" value="{{ $org->id }}">
                                    <button type="submit" @class(['flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm hover:bg-slate-50', 'bg-brand-50 font-semibold text-brand-800' => $org->id === $currentOrganization?->id])>
                                        <span class="truncate">{{ $org->name }}</span>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $org->typeLabel() }}</span>
                                    </button>
                                </form>
                            @endforeach
                            @can('plataforma')
                                <a href="{{ route('admin.organizations') }}" wire:navigate class="mt-1 flex items-center gap-2 border-t border-slate-100 px-4 pt-2 text-sm font-medium text-brand-700 hover:underline"><x-icon name="cog" class="size-4" /> Administrar entidades</a>
                            @endcan
                        </div>
                    </div>
                @endif
                <p class="hidden truncate text-sm font-medium text-slate-500 md:block">{{ $title ?? '' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.help') }}" wire:navigate class="btn-ghost btn-sm" title="Manual de uso"><x-icon name="help" class="size-4" /><span class="hidden sm:inline"> Ayuda</span></a>
                @if ($currentOrganization)
                    <a href="{{ $currentOrganization->url() }}" target="_blank" class="btn-ghost btn-sm hidden sm:inline-flex"><x-icon name="external" class="size-4" /> Ver sitio</a>
                @endif
                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-slate-100" x-on:click="open = !open">
                        @if (auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}" class="size-8 rounded-full object-cover" alt="">
                        @else
                            <span class="grid size-8 place-items-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ auth()->user()->initials() }}</span>
                        @endif
                        <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span>
                        <x-icon name="chevron-down" class="size-4 text-slate-400" />
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 rounded-xl bg-white py-2 shadow-lg ring-1 ring-slate-900/10">
                        <div class="border-b border-slate-100 px-4 pb-2">
                            <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <a href="{{ route('admin.profile') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><x-icon name="user" class="size-4" /> Mi perfil</a>
                        @if (auth()->user()->memberships()->exists())
                            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><x-icon name="id-card" class="size-4" /> Portal del socio</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</div>

<x-toasts />
</body>
</html>
