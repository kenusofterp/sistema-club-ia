@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    @include('partials.head', ['title' => $title ?? null, 'realtime' => true])
</head>
<body class="min-h-full font-sans antialiased text-slate-800">
{{-- Pantalla del juez (celular): sin menú lateral, solo el puesto asignado. --}}
<header class="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 bg-brand-900 px-4 text-white">
    <div class="flex min-w-0 items-center gap-2">
        <x-logo light :with-name="false" size="size-8" />
        <span class="truncate text-sm font-semibold">{{ setting('site.short_name') ?: setting('site.name') }} · Juez</span>
    </div>
    <div class="flex items-center gap-1">
        @if (auth()->user()->canAccessAdmin() && auth()->user()->can('dashboard.ver'))
            <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-2 py-1 text-xs text-white/80 hover:bg-white/10">Panel</a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg px-2 py-1 text-xs text-white/80 hover:bg-white/10" title="Cerrar sesión"><x-icon name="logout" class="size-5" /><span class="sr-only">Cerrar sesión</span></button>
        </form>
    </div>
</header>

<main class="mx-auto w-full max-w-xl px-4 py-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
    {{ $slot }}
</main>

<x-toasts />
</body>
</html>
